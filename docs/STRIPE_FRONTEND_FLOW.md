# Stripe — Flux Frontend (Payment Element / pm_xxx)

## Résumé
- **But**: permettre au front d’initier le paiement (Payment Element ou `pm_xxx`) et au backend de débiter en sécurité.
- **Sécurité**: le front n’a jamais la clé secrète. Le backend confirme et débite.
- **Compatibilité**: aucune route existante n’est modifiée. Deux nouveaux endpoints dédiés sont ajoutés.

## Prérequis
- Authentification Bearer (Sanctum): `Authorization: Bearer <token>`
- Base URL API (local): `http://localhost:8000`

## Endpoints (flux frontend)

### 1) Créer un PaymentIntent (récupérer client_secret)
- **POST** `/api/payments/stripe/frontend/intent`
- **Body**
  - `amount` number (requis)
  - `currency` string (optionnel, défaut: `EUR`)
  - `metadata` object (optionnel)
- **Réponse**
  - `client_secret`, `payment_intent_id`, `status`
- **Note**: `automatic_payment_methods.enabled = true` et `allow_redirects = 'never'` → pas de `return_url` requis pour les cartes.

Exemple requête:
```http
POST /api/payments/stripe/frontend/intent
Authorization: Bearer <token>
Content-Type: application/json

{
  "amount": 29.99,
  "currency": "EUR",
  "metadata": { "order_id": "ORD-123" }
}
```

### 2) Confirmer un PaymentIntent (débit réel)
- **POST** `/api/payments/stripe/frontend/confirm`
- **Body**
  - `payment_intent_id` string (requis)
  - `payment_method` string `pm_xxx` (optionnel si déjà fourni côté front)
  - `return_url` string (optionnel; utile si vous activez des moyens de paiement avec redirection)
- **Réponse**
  - `status` (`succeeded`, `requires_action`, ...), `payment_intent_id`, `response`

Exemple requête:
```http
POST /api/payments/stripe/frontend/confirm
Authorization: Bearer <token>
Content-Type: application/json

{
  "payment_intent_id": "pi_xxx",
  "payment_method": "pm_card_visa"
}
```

## Scénarios d’usage

- **A. Payment Element (recommandé)**
  1. Backend → créer l’intent (client_secret)
  2. Front → confirmer avec Stripe Payment Element

Exemple front (schéma minimal):
```js
const { error } = await stripe.confirmPayment({
  elements,
  clientSecret, // reçu de /intent
  confirmParams: { return_url: 'https://votre-front/success' } // optionnel pour cartes
});
```

- **B. pm_xxx (createPaymentMethod)**
  1. Front → `stripe.createPaymentMethod({ type: 'card', card: cardElement })` → `pm_xxx`
  2. Backend → `/confirm` avec `{ payment_intent_id, payment_method: 'pm_xxx' }`

## 3DS / Actions supplémentaires
- Si la réponse Stripe est `requires_action`, le front doit finaliser l’action via `stripe.confirmPayment(...)` avec le `client_secret`.
- Comme `allow_redirects = 'never'`, pas de redirection automatique côté Stripe pour les cartes.
- Si vous souhaitez activer des moyens “redirect-based” (iDEAL, Bancontact, ...):
  - Retirez `allow_redirects = 'never'` lors de la création de l’intent
  - Fournissez systématiquement `return_url` lors de la confirmation

## Compatibilité avec les routes existantes
- **Paiement avec carte enregistrée**: continuer d’utiliser `POST /api/payments` avec `payment_method_id`.
- Les nouveaux endpoints sont isolés sous `/api/payments/stripe/frontend/*`.

## Tests
- Cartes de test Stripe: `pm_card_visa`, `pm_card_mastercard`
- Swagger: `/api/documentation` (endpoints “Payments” → “frontend”)

## Références
- Accept a payment: https://docs.stripe.com/payments/accept-a-payment
- Payment Intents API: https://docs.stripe.com/api/payment_intents
- Create PaymentMethod: https://docs.stripe.com/api/payment_methods/create
- Confirm Payment: https://docs.stripe.com/js/payment_intents/confirm_payment
