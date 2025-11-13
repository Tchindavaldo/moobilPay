<?php

namespace App\Services\Payment;

use App\Models\User;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use PayPalCheckoutSdk\Orders\OrdersGetRequest;
use PayPalCheckoutSdk\Payments\CapturesRefundRequest;
use Illuminate\Support\Facades\Log;

class PayPalPaymentService implements PaymentServiceInterface
{
    private PayPalHttpClient $client;

    public function __construct()
    {
        $environment = config('services.paypal.mode') === 'live' 
            ? new ProductionEnvironment(
                config('services.paypal.client_id'),
                config('services.paypal.client_secret')
            )
            : new SandboxEnvironment(
                config('services.paypal.client_id'),
                config('services.paypal.client_secret')
            );

        $this->client = new PayPalHttpClient($environment);
    }

    public function getProvider(): string
    {
        return 'paypal';
    }

    public function createCustomer(User $user): array
    {
        // PayPal n'a pas de concept de "customer" comme Stripe
        // On retourne simplement les données de l'utilisateur
        return [
            'success' => true,
            'customer_id' => $user->email,
            'data' => [
                'email' => $user->email,
                'name' => $user->name,
            ],
        ];
    }

    public function createPaymentMethod(User $user, array $paymentData): PaymentMethod
    {
        // Pour PayPal, on crée une méthode de paiement basique
        // Les vraies informations de paiement sont gérées côté PayPal
        return PaymentMethod::create([
            'user_id' => $user->id,
            'provider' => 'paypal',
            'type' => 'paypal_account',
            'provider_id' => $user->email,
            'external_id' => $paymentData['payer_id'] ?? null,
            'metadata' => [
                'email' => $paymentData['email'] ?? $user->email,
                'payer_id' => $paymentData['payer_id'] ?? null,
            ],
            'is_default' => $paymentData['is_default'] ?? false,
            'is_active' => true,
        ]);
    }

    public function createPayment(User $user, array $paymentData): Payment
    {
        try {
            $request = new OrdersCreateRequest();
            $request->prefer('return=representation');
            $request->body = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => 'payment_' . time(),
                        'amount' => [
                            'currency_code' => strtoupper($paymentData['currency'] ?? 'EUR'),
                            'value' => number_format($paymentData['amount'], 2, '.', ''),
                        ],
                        'description' => $paymentData['description'] ?? 'Payment',
                    ]
                ],
                'application_context' => [
                    'return_url' => config('app.url') . '/api/payments/paypal/success',
                    'cancel_url' => config('app.url') . '/api/payments/paypal/cancel',
                    'brand_name' => config('app.name'),
                    'landing_page' => 'BILLING',
                    'user_action' => 'PAY_NOW',
                ],
            ];

            $response = $this->client->execute($request);

            return Payment::create([
                'user_id' => $user->id,
                'payment_method_id' => $paymentData['payment_method_id'] ?? null,
                'provider' => 'paypal',
                'provider_payment_id' => $response->result->id,
                'provider_customer_id' => $user->email,
                'amount' => $paymentData['amount'],
                'currency' => $paymentData['currency'] ?? 'eur',
                'status' => $this->mapPayPalStatus($response->result->status),
                'type' => 'payment',
                'description' => $paymentData['description'] ?? null,
                'metadata' => array_merge(
                    $paymentData['metadata'] ?? [],
                    ['approval_url' => $this->getApprovalUrl($response->result)]
                ),
                'provider_response' => json_decode(json_encode($response->result), true),
            ]);
        } catch (\Exception $e) {
            Log::error('PayPal payment creation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function confirmPayment(Payment $payment, array $confirmationData = []): Payment
    {
        try {
            $request = new OrdersCaptureRequest($payment->provider_payment_id);
            $request->prefer('return=representation');

            $response = $this->client->execute($request);

            $status = $this->mapPayPalStatus($response->result->status);
            $payment->update([
                'status' => $status,
                'provider_response' => json_decode(json_encode($response->result), true),
                'processed_at' => $status === 'succeeded' ? now() : null,
            ]);

            // Créer une transaction si le paiement est réussi
            if ($status === 'succeeded') {
                $this->createTransaction($payment, 'charge', $payment->amount);
            }

            return $payment;
        } catch (\Exception $e) {
            Log::error('PayPal payment confirmation failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            $payment->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function refundPayment(Payment $payment, float $amount = null): Payment
    {
        try {
            // Récupérer les détails de la commande pour obtenir l'ID de capture
            $orderRequest = new OrdersGetRequest($payment->provider_payment_id);
            $orderResponse = $this->client->execute($orderRequest);

            $captureId = $orderResponse->result->purchase_units[0]->payments->captures[0]->id ?? null;
            
            if (!$captureId) {
                throw new \Exception('No capture found for this payment');
            }

            $request = new CapturesRefundRequest($captureId);
            $request->prefer('return=representation');
            $request->body = [
                'amount' => [
                    'currency_code' => strtoupper($payment->currency),
                    'value' => number_format($amount ?? $payment->amount, 2, '.', ''),
                ],
                'note_to_payer' => 'Refund for payment ' . $payment->uuid,
            ];

            $response = $this->client->execute($request);

            // Créer un nouveau paiement pour le remboursement
            $refundPayment = Payment::create([
                'user_id' => $payment->user_id,
                'payment_method_id' => $payment->payment_method_id,
                'provider' => 'paypal',
                'provider_payment_id' => $response->result->id,
                'provider_customer_id' => $payment->provider_customer_id,
                'amount' => $amount ?? $payment->amount,
                'currency' => $payment->currency,
                'status' => $this->mapPayPalStatus($response->result->status),
                'type' => 'refund',
                'description' => 'Refund for payment ' . $payment->uuid,
                'provider_response' => json_decode(json_encode($response->result), true),
                'processed_at' => now(),
            ]);

            // Créer une transaction pour le remboursement
            $this->createTransaction($refundPayment, 'refund', $refundPayment->amount);

            return $refundPayment;
        } catch (\Exception $e) {
            Log::error('PayPal refund failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function retrievePayment(string $providerPaymentId): array
    {
        try {
            $request = new OrdersGetRequest($providerPaymentId);
            $response = $this->client->execute($request);
            return json_decode(json_encode($response->result), true);
        } catch (\Exception $e) {
            Log::error('PayPal payment retrieval failed', [
                'order_id' => $providerPaymentId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function deletePaymentMethod(PaymentMethod $paymentMethod): bool
    {
        // PayPal ne permet pas de "supprimer" une méthode de paiement
        // On la désactive simplement
        $paymentMethod->update(['is_active' => false]);
        return true;
    }

    private function getApprovalUrl($order): ?string
    {
        foreach ($order->links as $link) {
            if ($link->rel === 'approve') {
                return $link->href;
            }
        }
        return null;
    }

    private function mapPayPalStatus(string $paypalStatus): string
    {
        return match(strtoupper($paypalStatus)) {
            'CREATED', 'SAVED', 'APPROVED', 'PAYER_ACTION_REQUIRED' => 'pending',
            'COMPLETED' => 'succeeded',
            'CANCELLED' => 'canceled',
            'FAILED' => 'failed',
            default => 'pending',
        };
    }

    private function createTransaction(Payment $payment, string $type, float $amount): Transaction
    {
        return Transaction::create([
            'payment_id' => $payment->id,
            'type' => $type,
            'amount' => $amount,
            'currency' => $payment->currency,
            'status' => 'succeeded',
            'provider_transaction_id' => $payment->provider_payment_id,
            'provider_response' => $payment->provider_response,
            'processed_at' => now(),
        ]);
    }
}
