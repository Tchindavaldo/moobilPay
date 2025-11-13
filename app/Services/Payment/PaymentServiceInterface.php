<?php

namespace App\Services\Payment;

use App\Models\User;
use App\Models\Payment;
use App\Models\PaymentMethod;

interface PaymentServiceInterface
{
    public function createCustomer(User $user): array;
    
    public function createPaymentMethod(User $user, array $paymentData): PaymentMethod;
    
    public function createPayment(User $user, array $paymentData): Payment;
    
    public function confirmPayment(Payment $payment, array $confirmationData = []): Payment;
    
    public function refundPayment(Payment $payment, float $amount = null): Payment;
    
    public function retrievePayment(string $providerPaymentId): array;
    
    public function deletePaymentMethod(PaymentMethod $paymentMethod): bool;
    
    public function getProvider(): string;
}
