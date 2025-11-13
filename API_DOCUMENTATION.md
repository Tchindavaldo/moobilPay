# API de Paiement MoobilPay

## Vue d'ensemble

Cette API permet de gérer les paiements en ligne via Stripe et PayPal. Elle offre une interface unifiée pour traiter les paiements, gérer les méthodes de paiement et recevoir les webhooks.

## Configuration

### Variables d'environnement

Copiez `.env.example` vers `.env` et configurez les variables suivantes :

```bash
# Base de données PostgreSQL
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=moobil_pay
DB_USERNAME=postgres
DB_PASSWORD=votre_mot_de_passe

# Stripe
STRIPE_PUBLISHABLE_KEY=pk_test_...
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# PayPal
PAYPAL_MODE=sandbox
PAYPAL_CLIENT_ID=votre_client_id
PAYPAL_CLIENT_SECRET=votre_client_secret
```

### Installation

```bash
composer install
php artisan migrate
php artisan key:generate
```

## Authentification

Toutes les routes API (sauf les webhooks) nécessitent une authentification via Laravel Sanctum.

```bash
Authorization: Bearer {token}
```

## Endpoints

### Paiements

#### Lister les paiements
```http
GET /api/payments
```

**Paramètres de requête :**
- `status` : pending, processing, succeeded, failed, canceled, refunded
- `provider` : stripe, paypal
- `type` : payment, refund, subscription
- `from_date` : YYYY-MM-DD
- `to_date` : YYYY-MM-DD

#### Créer un paiement
```http
POST /api/payments
```

**Body :**
```json
{
  "provider": "stripe",
  "amount": 29.99,
  "currency": "EUR",
  "description": "Achat produit",
  "payment_method_id": 1,
  "auto_confirm": true,
  "metadata": {
    "order_id": "12345"
  }
}
```

#### Détails d'un paiement
```http
GET /api/payments/{uuid}
```

#### Confirmer un paiement
```http
POST /api/payments/{uuid}/confirm
```

**Body :**
```json
{
  "confirmation_data": {
    "payment_method": "pm_card_visa"
  }
}
```

#### Rembourser un paiement
```http
POST /api/payments/{uuid}/refund
```

**Body :**
```json
{
  "amount": 15.00,
  "reason": "Demande client"
}
```

#### Statistiques des paiements
```http
GET /api/payments/stats
```

### Méthodes de paiement

#### Lister les méthodes de paiement
```http
GET /api/payment-methods?provider=stripe
```

#### Ajouter une méthode de paiement

**Stripe :**
```http
POST /api/payment-methods
```

```json
{
  "provider": "stripe",
  "payment_method_id": "pm_card_visa",
  "is_default": true
}
```

**PayPal :**
```http
POST /api/payment-methods
```

```json
{
  "provider": "paypal",
  "email": "user@example.com",
  "payer_id": "PAYERID123",
  "is_default": false
}
```

#### Détails d'une méthode de paiement
```http
GET /api/payment-methods/{id}
```

#### Modifier une méthode de paiement
```http
PUT /api/payment-methods/{id}
```

```json
{
  "is_default": true,
  "is_active": true
}
```

#### Supprimer une méthode de paiement
```http
DELETE /api/payment-methods/{id}
```

#### Définir comme méthode par défaut
```http
POST /api/payment-methods/{id}/set-default
```

### Webhooks

#### Webhook Stripe
```http
POST /api/webhooks/stripe
```

#### Webhook PayPal
```http
POST /api/webhooks/paypal
```

## Codes de réponse

- `200` : Succès
- `201` : Créé avec succès
- `400` : Erreur de validation
- `401` : Non authentifié
- `404` : Ressource non trouvée
- `422` : Erreur de validation
- `500` : Erreur serveur

## Format des réponses

### Succès
```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": { ... },
  "meta": { ... }
}
```

### Erreur
```json
{
  "success": false,
  "message": "Error description",
  "error": "Detailed error message",
  "errors": { ... }
}
```

## Exemples d'utilisation

### Flux de paiement Stripe

1. **Créer une méthode de paiement côté client avec Stripe Elements**
2. **Enregistrer la méthode de paiement :**
```javascript
const response = await fetch('/api/payment-methods', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer ' + token,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    provider: 'stripe',
    payment_method_id: 'pm_card_visa',
    is_default: true
  })
});
```

3. **Créer un paiement :**
```javascript
const payment = await fetch('/api/payments', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer ' + token,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    provider: 'stripe',
    amount: 29.99,
    currency: 'EUR',
    payment_method_id: 1,
    auto_confirm: true
  })
});
```

### Flux de paiement PayPal

1. **Créer un paiement :**
```javascript
const payment = await fetch('/api/payments', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer ' + token,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    provider: 'paypal',
    amount: 29.99,
    currency: 'EUR',
    description: 'Achat produit'
  })
});
```

2. **Rediriger vers PayPal avec l'URL d'approbation**
3. **Confirmer le paiement après retour :**
```javascript
const confirmation = await fetch(`/api/payments/${payment.data.uuid}/confirm`, {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer ' + token,
    'Content-Type': 'application/json'
  }
});
```

## Sécurité

- Toutes les communications doivent utiliser HTTPS en production
- Les clés API doivent être stockées de manière sécurisée
- Les webhooks Stripe sont vérifiés par signature
- Les montants sont validés côté serveur
- Les données sensibles sont chiffrées en base

## Support

Pour toute question ou problème, consultez les logs de l'application ou contactez l'équipe de développement.
