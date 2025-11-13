<?php

namespace App\Services\Payment;

use InvalidArgumentException;

class PaymentServiceFactory
{
    public static function create(string $provider): PaymentServiceInterface
    {
        return match($provider) {
            'stripe' => new StripePaymentService(),
            'paypal' => new PayPalPaymentService(),
            default => throw new InvalidArgumentException("Unsupported payment provider: {$provider}"),
        };
    }

    public static function getSupportedProviders(): array
    {
        return ['stripe', 'paypal'];
    }
}
