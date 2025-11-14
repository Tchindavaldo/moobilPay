<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Models\Payment;
use App\Services\Payment\PaymentServiceFactory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook as StripeWebhook;
use Stripe\Exception\SignatureVerificationException;

class WebhookController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/webhooks/stripe",
     *     summary="Webhook Stripe",
     *     description="Réception des événements de paiement Stripe",
     *     tags={"Webhooks"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Payload du webhook Stripe",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="string", example="evt_1234567890"),
     *             @OA\Property(property="type", type="string", example="payment_intent.succeeded"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Webhook traité avec succès"),
     *     @OA\Response(response=400, description="Erreur de signature ou payload invalide")
     * )
     */
    public function stripe(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        try {
            // Vérifier la signature du webhook
            $event = StripeWebhook::constructEvent(
                $payload,
                $sigHeader,
                $endpointSecret
            );
        } catch (\UnexpectedValueException $e) {
            Log::error('Stripe webhook: Invalid payload', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (SignatureVerificationException $e) {
            Log::error('Stripe webhook: Invalid signature', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Créer l'enregistrement webhook
        $webhook = Webhook::create([
            'provider' => 'stripe',
            'event_type' => $event->type,
            'provider_event_id' => $event->id,
            'payload' => $event->data->toArray(),
            'status' => 'pending',
        ]);

        try {
            $this->processStripeWebhook($event, $webhook);
            $webhook->markAsProcessed();
            
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Stripe webhook processing failed', [
                'event_id' => $event->id,
                'event_type' => $event->type,
                'error' => $e->getMessage(),
            ]);
            
            $webhook->markAsFailed($e->getMessage());
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/webhooks/paypal",
     *     summary="Webhook PayPal",
     *     description="Réception des événements de paiement PayPal",
     *     tags={"Webhooks"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Payload du webhook PayPal",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="string", example="WH-1234567890"),
     *             @OA\Property(property="event_type", type="string", example="PAYMENT.CAPTURE.COMPLETED"),
     *             @OA\Property(property="resource", type="object")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Webhook traité avec succès"),
     *     @OA\Response(response=400, description="Erreur de validation du webhook")
     * )
     */
    public function paypal(Request $request): JsonResponse
    {
        $payload = $request->all();
        $headers = $request->headers->all();

        // Créer l'enregistrement webhook
        $webhook = Webhook::create([
            'provider' => 'paypal',
            'event_type' => $payload['event_type'] ?? 'unknown',
            'provider_event_id' => $payload['id'] ?? uniqid(),
            'payload' => $payload,
            'status' => 'pending',
        ]);

        try {
            $this->processPayPalWebhook($payload, $webhook);
            $webhook->markAsProcessed();
            
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('PayPal webhook processing failed', [
                'event_type' => $payload['event_type'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            
            $webhook->markAsFailed($e->getMessage());
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    private function processStripeWebhook($event, Webhook $webhook): void
    {
        $webhook->incrementAttempts();

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->handleStripePaymentSucceeded($event->data->object);
                break;
                
            case 'payment_intent.payment_failed':
                $this->handleStripePaymentFailed($event->data->object);
                break;
                
            case 'payment_intent.canceled':
                $this->handleStripePaymentCanceled($event->data->object);
                break;
                
            case 'charge.dispute.created':
                $this->handleStripeChargeDispute($event->data->object);
                break;
                
            default:
                Log::info('Unhandled Stripe webhook event', [
                    'event_type' => $event->type,
                    'event_id' => $event->id,
                ]);
        }
    }

    private function processPayPalWebhook(array $payload, Webhook $webhook): void
    {
        $webhook->incrementAttempts();

        $eventType = $payload['event_type'] ?? '';

        switch ($eventType) {
            case 'PAYMENT.CAPTURE.COMPLETED':
                $this->handlePayPalPaymentCompleted($payload);
                break;
                
            case 'PAYMENT.CAPTURE.DENIED':
                $this->handlePayPalPaymentDenied($payload);
                break;
                
            case 'PAYMENT.CAPTURE.REFUNDED':
                $this->handlePayPalPaymentRefunded($payload);
                break;
                
            default:
                Log::info('Unhandled PayPal webhook event', [
                    'event_type' => $eventType,
                    'event_id' => $payload['id'] ?? 'unknown',
                ]);
        }
    }

    private function handleStripePaymentSucceeded($paymentIntent): void
    {
        $payment = Payment::where('provider_payment_id', $paymentIntent->id)->first();
        
        if ($payment && $payment->status !== 'succeeded') {
            $payment->update([
                'status' => 'succeeded',
                'processed_at' => now(),
                'provider_response' => $paymentIntent,
            ]);

            Log::info('Stripe payment succeeded', ['payment_id' => $payment->id]);
        }
    }

    private function handleStripePaymentFailed($paymentIntent): void
    {
        $payment = Payment::where('provider_payment_id', $paymentIntent->id)->first();
        
        if ($payment && !$payment->isFailed()) {
            $payment->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => $paymentIntent->last_payment_error->message ?? 'Payment failed',
                'provider_response' => $paymentIntent,
            ]);

            Log::info('Stripe payment failed', ['payment_id' => $payment->id]);
        }
    }

    private function handleStripePaymentCanceled($paymentIntent): void
    {
        $payment = Payment::where('provider_payment_id', $paymentIntent->id)->first();
        
        if ($payment && $payment->status !== 'canceled') {
            $payment->update([
                'status' => 'canceled',
                'provider_response' => $paymentIntent,
            ]);

            Log::info('Stripe payment canceled', ['payment_id' => $payment->id]);
        }
    }

    private function handleStripeChargeDispute($dispute): void
    {
        $payment = Payment::where('provider_payment_id', $dispute->payment_intent)->first();
        
        if ($payment) {
            Log::warning('Stripe charge dispute created', [
                'payment_id' => $payment->id,
                'dispute_id' => $dispute->id,
                'amount' => $dispute->amount,
                'reason' => $dispute->reason,
            ]);
        }
    }

    private function handlePayPalPaymentCompleted(array $payload): void
    {
        $orderId = $payload['resource']['supplementary_data']['related_ids']['order_id'] ?? null;
        
        if ($orderId) {
            $payment = Payment::where('provider_payment_id', $orderId)->first();
            
            if ($payment && $payment->status !== 'succeeded') {
                $payment->update([
                    'status' => 'succeeded',
                    'processed_at' => now(),
                    'provider_response' => $payload,
                ]);

                Log::info('PayPal payment completed', ['payment_id' => $payment->id]);
            }
        }
    }

    private function handlePayPalPaymentDenied(array $payload): void
    {
        $orderId = $payload['resource']['supplementary_data']['related_ids']['order_id'] ?? null;
        
        if ($orderId) {
            $payment = Payment::where('provider_payment_id', $orderId)->first();
            
            if ($payment && !$payment->isFailed()) {
                $payment->update([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'failure_reason' => 'Payment denied by PayPal',
                    'provider_response' => $payload,
                ]);

                Log::info('PayPal payment denied', ['payment_id' => $payment->id]);
            }
        }
    }

    private function handlePayPalPaymentRefunded(array $payload): void
    {
        $orderId = $payload['resource']['supplementary_data']['related_ids']['order_id'] ?? null;
        
        if ($orderId) {
            $payment = Payment::where('provider_payment_id', $orderId)->first();
            
            if ($payment) {
                Log::info('PayPal payment refunded', [
                    'payment_id' => $payment->id,
                    'refund_amount' => $payload['resource']['amount']['value'] ?? 'unknown',
                ]);
            }
        }
    }
}
