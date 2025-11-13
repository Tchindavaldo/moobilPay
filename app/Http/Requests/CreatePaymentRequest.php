<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => 'required|in:stripe,paypal',
            'amount' => 'required|numeric|min:0.01|max:999999.99',
            'currency' => 'sometimes|string|size:3|in:EUR,USD,GBP,CAD',
            'description' => 'sometimes|string|max:255',
            'payment_method_id' => 'sometimes|exists:payment_methods,id',
            'auto_confirm' => 'sometimes|boolean',
            'metadata' => 'sometimes|array',
            'metadata.*' => 'string|max:500',
            'confirmation_data' => 'sometimes|array',
        ];
    }

    public function messages(): array
    {
        return [
            'provider.required' => 'Le fournisseur de paiement est requis.',
            'provider.in' => 'Le fournisseur doit être stripe ou paypal.',
            'amount.required' => 'Le montant est requis.',
            'amount.numeric' => 'Le montant doit être un nombre.',
            'amount.min' => 'Le montant minimum est de 0.01.',
            'amount.max' => 'Le montant maximum est de 999999.99.',
            'currency.size' => 'La devise doit faire exactement 3 caractères.',
            'currency.in' => 'Devise non supportée.',
            'payment_method_id.exists' => 'La méthode de paiement n\'existe pas.',
            'description.max' => 'La description ne peut pas dépasser 255 caractères.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Normaliser la devise en majuscules
        if ($this->has('currency')) {
            $this->merge([
                'currency' => strtoupper($this->currency),
            ]);
        }

        // Valeur par défaut pour la devise
        if (!$this->has('currency')) {
            $this->merge(['currency' => 'EUR']);
        }
    }
}
