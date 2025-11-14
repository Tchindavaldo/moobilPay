<?php

namespace App\Services\Payment;

use App\Models\User;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;
use Illuminate\Support\Facades\Log;

class StripePaymentService implements PaymentServiceInterface
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Create a PaymentIntent and return its client_secret for frontend confirmation (Payment Element flow)
     */
    public function createFrontendPaymentIntent(array $data): array
    {
        try {
            $amountInCents = (int) ($data['amount'] * 100);

            $payload = [
                'amount' => $amountInCents,
                'currency' => $data['currency'] ?? 'eur',
                'metadata' => $data['metadata'] ?? [],
                // Avoid redirect-based methods so return_url is not mandatory in backend confirmations
                'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
            ];

            $pi = $this->stripe->paymentIntents->create($payload);

            return [
                'success' => true,
                'payment_intent_id' => $pi->id,
                'client_secret' => $pi->client_secret,
                'status' => $pi->status,
            ];
        } catch (\Exception $e) {
            Log::error('Stripe frontend PI creation failed', [
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Confirm a PaymentIntent using a frontend-provided payment_method (pm_...) token
     */
    public function confirmFrontendPaymentIntent(string $paymentIntentId, array $data): array
    {
        try {
            $params = [];
            if (!empty($data['payment_method'])) {
                $params['payment_method'] = $data['payment_method'];
            }
            if (!empty($data['return_url'])) {
                $params['return_url'] = $data['return_url'];
            }

            $pi = $this->stripe->paymentIntents->confirm($paymentIntentId, $params);

            return [
                'success' => true,
                'payment_intent_id' => $pi->id,
                'status' => $pi->status,
                'response' => $pi->toArray(),
            ];
        } catch (\Exception $e) {
            Log::error('Stripe frontend PI confirm failed', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getProvider(): string
    {
        return 'stripe';
    }

    public function createCustomer(User $user): array
    {
        try {
            $customer = $this->stripe->customers->create([
                'email' => $user->email,
                'name' => $user->name,
                'metadata' => [
                    'user_id' => $user->id,
                ],
            ]);

            return [
                'success' => true,
                'customer_id' => $customer->id,
                'data' => $customer->toArray(),
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe customer creation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function createPaymentMethod(User $user, array $paymentData): PaymentMethod
    {
        try {
            // Créer ou récupérer le customer Stripe
            $customerResult = $this->createCustomer($user);
            if (!$customerResult['success']) {
                throw new \Exception($customerResult['error']);
            }

            $customerId = $customerResult['customer_id'];

            // Attacher la méthode de paiement au customer
            $this->stripe->paymentMethods->attach($paymentData['payment_method_id'], [
                'customer' => $customerId,
            ]);

            // Récupérer les détails de la méthode de paiement
            $stripePaymentMethod = $this->stripe->paymentMethods->retrieve($paymentData['payment_method_id']);

            // Déterminer le type et les métadonnées
            $type = $this->getPaymentMethodType($stripePaymentMethod);
            $metadata = $this->getPaymentMethodMetadata($stripePaymentMethod);

            return PaymentMethod::create([
                'user_id' => $user->id,
                'provider' => 'stripe',
                'type' => $type,
                'provider_id' => $customerId,
                'external_id' => $stripePaymentMethod->id,
                'metadata' => $metadata,
                'is_default' => $paymentData['is_default'] ?? false,
                'is_active' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Stripe payment method creation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function createPayment(User $user, array $paymentData): Payment
    {
        try {
            // Convertir le montant en centimes
            $amountInCents = (int) ($paymentData['amount'] * 100);

            $paymentIntentData = [
                'amount' => $amountInCents,
                'currency' => $paymentData['currency'] ?? 'eur',
                'metadata' => [
                    'user_id' => $user->id,
                    'description' => $paymentData['description'] ?? '',
                ],
            ];

            // Si une méthode de paiement est spécifiée
            if (isset($paymentData['payment_method_id'])) {
                $paymentMethod = PaymentMethod::findOrFail($paymentData['payment_method_id']);
                $paymentIntentData['customer'] = $paymentMethod->provider_id;
                $paymentIntentData['payment_method'] = $paymentMethod->external_id;
                $paymentIntentData['confirmation_method'] = 'manual';
                $paymentIntentData['confirm'] = true;
            }

            $paymentIntent = $this->stripe->paymentIntents->create($paymentIntentData);

            return Payment::create([
                'user_id' => $user->id,
                'payment_method_id' => $paymentData['payment_method_id'] ?? null,
                'provider' => 'stripe',
                'provider_payment_id' => $paymentIntent->id,
                'provider_customer_id' => $paymentIntent->customer ?? null,
                'amount' => $paymentData['amount'],
                'currency' => $paymentData['currency'] ?? 'eur',
                'status' => $this->mapStripeStatus($paymentIntent->status),
                'type' => 'payment',
                'description' => $paymentData['description'] ?? null,
                'metadata' => $paymentData['metadata'] ?? null,
                'provider_response' => $paymentIntent->toArray(),
            ]);
        } catch (\Exception $e) {
            Log::error('Stripe payment creation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function confirmPayment(Payment $payment, array $confirmationData = []): Payment
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->confirm(
                $payment->provider_payment_id,
                $confirmationData
            );

            $payment->update([
                'status' => $this->mapStripeStatus($paymentIntent->status),
                'provider_response' => $paymentIntent->toArray(),
                'processed_at' => $paymentIntent->status === 'succeeded' ? now() : null,
            ]);

            // Créer une transaction si le paiement est réussi
            if ($paymentIntent->status === 'succeeded') {
                $this->createTransaction($payment, 'charge', $payment->amount);
            }

            return $payment;
        } catch (\Exception $e) {
            Log::error('Stripe payment confirmation failed', [
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
            $refundData = [
                'payment_intent' => $payment->provider_payment_id,
            ];

            if ($amount) {
                $refundData['amount'] = (int) ($amount * 100);
            }

            $refund = $this->stripe->refunds->create($refundData);

            // Créer un nouveau paiement pour le remboursement
            $refundPayment = Payment::create([
                'user_id' => $payment->user_id,
                'payment_method_id' => $payment->payment_method_id,
                'provider' => 'stripe',
                'provider_payment_id' => $refund->id,
                'provider_customer_id' => $payment->provider_customer_id,
                'amount' => $amount ?? $payment->amount,
                'currency' => $payment->currency,
                'status' => $this->mapStripeStatus($refund->status),
                'type' => 'refund',
                'description' => 'Refund for payment ' . $payment->uuid,
                'provider_response' => $refund->toArray(),
                'processed_at' => $refund->status === 'succeeded' ? now() : null,
            ]);

            // Créer une transaction pour le remboursement
            $this->createTransaction($refundPayment, 'refund', $refundPayment->amount);

            return $refundPayment;
        } catch (\Exception $e) {
            Log::error('Stripe refund failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function retrievePayment(string $providerPaymentId): array
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->retrieve($providerPaymentId);
            return $paymentIntent->toArray();
        } catch (\Exception $e) {
            Log::error('Stripe payment retrieval failed', [
                'payment_intent_id' => $providerPaymentId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function deletePaymentMethod(PaymentMethod $paymentMethod): bool
    {
        try {
            $this->stripe->paymentMethods->detach($paymentMethod->external_id);
            $paymentMethod->update(['is_active' => false]);
            return true;
        } catch (\Exception $e) {
            Log::error('Stripe payment method deletion failed', [
                'payment_method_id' => $paymentMethod->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function getPaymentMethodType($stripePaymentMethod): string
    {
        return match($stripePaymentMethod->type) {
            'card' => 'card',
            'sepa_debit', 'us_bank_account' => 'bank_account',
            default => 'card',
        };
    }

    private function getPaymentMethodMetadata($stripePaymentMethod): array
    {
        $metadata = [];

        if ($stripePaymentMethod->type === 'card' && isset($stripePaymentMethod->card)) {
            $metadata = [
                'brand' => $stripePaymentMethod->card->brand,
                'last4' => $stripePaymentMethod->card->last4,
                'exp_month' => $stripePaymentMethod->card->exp_month,
                'exp_year' => $stripePaymentMethod->card->exp_year,
                'country' => $stripePaymentMethod->card->country,
            ];
        }

        return $metadata;
    }

    private function mapStripeStatus(string $stripeStatus): string
    {
        return match($stripeStatus) {
            'requires_payment_method', 'requires_confirmation', 'requires_action' => 'pending',
            'processing' => 'processing',
            'succeeded' => 'succeeded',
            'canceled' => 'canceled',
            'requires_capture' => 'pending',
            default => 'failed',
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
