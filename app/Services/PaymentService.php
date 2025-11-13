<?php

namespace App\Services;

use App\Models\User;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\Payment\PaymentServiceFactory;
use App\Services\Payment\PaymentServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function createPaymentMethod(User $user, string $provider, array $paymentData): PaymentMethod
    {
        $service = PaymentServiceFactory::create($provider);
        
        return DB::transaction(function () use ($service, $user, $paymentData) {
            // Si c'est la méthode par défaut, désactiver les autres
            if ($paymentData['is_default'] ?? false) {
                $user->paymentMethods()
                    ->where('provider', $service->getProvider())
                    ->update(['is_default' => false]);
            }

            return $service->createPaymentMethod($user, $paymentData);
        });
    }

    public function processPayment(User $user, array $paymentData): Payment
    {
        $provider = $paymentData['provider'] ?? 'stripe';
        $service = PaymentServiceFactory::create($provider);

        return DB::transaction(function () use ($service, $user, $paymentData) {
            $payment = $service->createPayment($user, $paymentData);

            // Auto-confirmer si demandé et si les données sont suffisantes
            if (($paymentData['auto_confirm'] ?? false) && isset($paymentData['payment_method_id'])) {
                $payment = $service->confirmPayment($payment, $paymentData['confirmation_data'] ?? []);
            }

            return $payment;
        });
    }

    public function confirmPayment(Payment $payment, array $confirmationData = []): Payment
    {
        $service = PaymentServiceFactory::create($payment->provider);
        
        return DB::transaction(function () use ($service, $payment, $confirmationData) {
            return $service->confirmPayment($payment, $confirmationData);
        });
    }

    public function refundPayment(Payment $payment, float $amount = null): Payment
    {
        if (!$payment->canBeRefunded()) {
            throw new \Exception('Payment cannot be refunded');
        }

        $service = PaymentServiceFactory::create($payment->provider);
        
        return DB::transaction(function () use ($service, $payment, $amount) {
            return $service->refundPayment($payment, $amount);
        });
    }

    public function deletePaymentMethod(PaymentMethod $paymentMethod): bool
    {
        $service = PaymentServiceFactory::create($paymentMethod->provider);
        
        return DB::transaction(function () use ($service, $paymentMethod) {
            $success = $service->deletePaymentMethod($paymentMethod);
            
            if ($success && $paymentMethod->is_default) {
                // Si c'était la méthode par défaut, en définir une autre
                $newDefault = $paymentMethod->user
                    ->paymentMethods()
                    ->where('provider', $paymentMethod->provider)
                    ->where('id', '!=', $paymentMethod->id)
                    ->active()
                    ->first();
                
                if ($newDefault) {
                    $newDefault->update(['is_default' => true]);
                }
            }
            
            return $success;
        });
    }

    public function getUserPaymentMethods(User $user, string $provider = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = $user->paymentMethods()->active();
        
        if ($provider) {
            $query->where('provider', $provider);
        }
        
        return $query->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getUserPayments(User $user, array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = $user->payments()->with(['paymentMethod', 'transactions']);
        
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['provider'])) {
            $query->where('provider', $filters['provider']);
        }
        
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        
        if (isset($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }
        
        if (isset($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }
        
        return $query->orderBy('created_at', 'desc')->get();
    }

    public function getPaymentStats(User $user): array
    {
        $payments = $user->payments();
        
        return [
            'total_payments' => $payments->count(),
            'successful_payments' => $payments->successful()->count(),
            'failed_payments' => $payments->failed()->count(),
            'pending_payments' => $payments->pending()->count(),
            'total_amount' => $payments->successful()->sum('amount'),
            'total_refunded' => $payments->where('type', 'refund')->sum('amount'),
            'by_provider' => [
                'stripe' => $payments->byProvider('stripe')->successful()->sum('amount'),
                'paypal' => $payments->byProvider('paypal')->successful()->sum('amount'),
            ],
        ];
    }

    public function setDefaultPaymentMethod(PaymentMethod $paymentMethod): bool
    {
        return DB::transaction(function () use ($paymentMethod) {
            // Désactiver toutes les autres méthodes par défaut pour ce provider
            $paymentMethod->user
                ->paymentMethods()
                ->where('provider', $paymentMethod->provider)
                ->where('id', '!=', $paymentMethod->id)
                ->update(['is_default' => false]);
            
            // Activer cette méthode comme défaut
            return $paymentMethod->update(['is_default' => true]);
        });
    }
}
