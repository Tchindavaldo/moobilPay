<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $provider = $this->input('provider');

        $rules = [
            'provider' => 'required|in:stripe,paypal',
            'is_default' => 'sometimes|boolean',
        ];

        // Règles spécifiques à Stripe
        if ($provider === 'stripe') {
            $rules['payment_method_id'] = 'required|string|min:3|max:255';
        }

        // Règles spécifiques à PayPal
        if ($provider === 'paypal') {
            $rules['payer_id'] = 'sometimes|string|min:3|max:255';
            $rules['email'] = 'required|email|max:255';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'provider.required' => 'Le fournisseur de paiement est requis.',
            'provider.in' => 'Le fournisseur doit être stripe ou paypal.',
            'payment_method_id.required' => 'L\'ID de la méthode de paiement Stripe est requis.',
            'payment_method_id.string' => 'L\'ID de la méthode de paiement doit être une chaîne.',
            'payment_method_id.min' => 'L\'ID de la méthode de paiement est trop court.',
            'payment_method_id.max' => 'L\'ID de la méthode de paiement est trop long.',
            'email.required' => 'L\'email PayPal est requis.',
            'email.email' => 'L\'email doit être valide.',
            'email.max' => 'L\'email ne peut pas dépasser 255 caractères.',
            'payer_id.string' => 'L\'ID du payeur doit être une chaîne.',
            'payer_id.min' => 'L\'ID du payeur est trop court.',
            'payer_id.max' => 'L\'ID du payeur est trop long.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Normaliser l'email en minuscules
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim($this->email)),
            ]);
        }
    }
}
