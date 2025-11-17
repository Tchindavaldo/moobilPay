# MoobilPay - Plateforme de Paiement Unifiée

<p align="center">
  <strong>Une API complète de gestion des paiements en ligne supportant Stripe et PayPal</strong>
</p>

## 📋 Table des matières

- [À propos](#-à-propos)
- [Fonctionnalités](#-fonctionnalités)
- [Architecture](#-architecture)
- [Prérequis](#-prérequis)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Utilisation](#-utilisation)
- [API Endpoints](#-api-endpoints)
- [Authentification](#-authentification)
- [Exemples d'utilisation](#-exemples-dutilisation)
- [Structure du projet](#-structure-du-projet)
- [Sécurité](#-sécurité)
- [Tests](#-tests)
- [Dépannage](#-dépannage)
- [Licence](#-licence)

## 🎯 À propos

**MoobilPay** est une plateforme de paiement moderne construite avec Laravel 12 qui offre une interface unifiée pour gérer les paiements en ligne. Elle supporte deux des plus grands fournisseurs de paiement : **Stripe** et **PayPal**.

### Cas d'usage

- Traitement des paiements uniques
- Gestion des méthodes de paiement sauvegardées
- Remboursements partiels ou totaux
- Webhooks pour les mises à jour en temps réel
- Authentification sécurisée avec Laravel Sanctum
- Statistiques et rapports de paiement

## ✨ Fonctionnalités

### Gestion des paiements
- **Création de paiements** : Support de Stripe et PayPal
- **Confirmation de paiements** : Flux de confirmation flexible
- **Remboursements** : Remboursements partiels ou complets
- **Historique** : Suivi complet des transactions
- **Statistiques** : Rapports détaillés par période et fournisseur

### Méthodes de paiement
- **Enregistrement** : Sauvegarde sécurisée des méthodes de paiement
- **Gestion** : Modification, suppression, définition par défaut
- **Support multi-fournisseur** : Stripe et PayPal
- **Authentification** : Vérification sécurisée des paiements

### Flux de paiement Stripe
- **Payment Intent** : Création d'intentions de paiement
- **Payment Element** : Interface de paiement moderne
- **Confirmation** : Confirmation côté serveur sécurisée
- **Webhooks** : Mise à jour automatique des statuts

### Flux de paiement PayPal
- **Création de paiement** : Génération d'URL d'approbation
- **Redirection** : Flux de redirection PayPal
- **Confirmation** : Confirmation après retour de PayPal
- **Webhooks** : Notifications en temps réel

## 🏗️ Architecture

### Stack technologique

- **Backend** : Laravel 12 (PHP 8.2+)
- **Base de données** : PostgreSQL
- **Authentification** : Laravel Sanctum
- **Paiements** : Stripe SDK, PayPal SDK
- **Documentation** : Swagger/OpenAPI (L5-Swagger)
- **Testing** : PHPUnit
- **Frontend** : Vite + Vue.js (optionnel)

### Structure modulaire

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── PaymentController.php
│   │       ├── PaymentMethodController.php
│   │       ├── StripeFrontendController.php
│   │       ├── WebhookController.php
│   │       ├── PayPalController.php
│   │       ├── AuthController.php
│   │       └── UserController.php
│   └── Requests/
│       ├── StorePaymentRequest.php
│       └── StorePaymentMethodRequest.php
├── Models/
│   ├── User.php
│   ├── Payment.php
│   ├── PaymentMethod.php
│   ├── Transaction.php
│   └── Webhook.php
└── Services/
    ├── PaymentService.php
    └── Payment/
        ├── StripePaymentService.php
        ├── PayPalPaymentService.php
        ├── PaymentProcessor.php
        └── WebhookHandler.php
```

## 📦 Prérequis

- **PHP** : 8.2 ou supérieur
- **Composer** : 2.0 ou supérieur
- **PostgreSQL** : 12 ou supérieur
- **Node.js** : 18+ (pour Vite)
- **Comptes** : Stripe et PayPal (sandbox ou production)

## 🚀 Installation

### 1. Cloner le repository

```bash
git clone <repository-url>
cd moobilPay
```

### 2. Installer les dépendances PHP

```bash
composer install
```

### 3. Installer les dépendances Node.js

```bash
npm install
```

### 4. Configuration automatique (recommandé)

```bash
composer run-script setup
```

Cette commande effectue automatiquement :
- Installation des dépendances Composer
- Copie du fichier `.env.example` vers `.env`
- Génération de la clé d'application
- Exécution des migrations
- Installation des dépendances npm
- Construction des assets Vite

### 5. Configuration manuelle (alternative)

```bash
# Copier le fichier d'environnement
cp .env.example .env

# Générer la clé d'application
php artisan key:generate

# Exécuter les migrations
php artisan migrate

# Construire les assets
npm run build
```

## ⚙️ Configuration

### Variables d'environnement essentielles

Éditez le fichier `.env` avec vos paramètres :

#### Base de données PostgreSQL
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=moobil_pay
DB_USERNAME=postgres
DB_PASSWORD=votre_mot_de_passe
```

#### Configuration Stripe
```env
STRIPE_PUBLISHABLE_KEY=pk_test_...
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

**Obtenir vos clés Stripe** :
1. Créer un compte sur [Stripe Dashboard](https://dashboard.stripe.com)
2. Aller à Developers → API Keys
3. Copier les clés de test (ou production)
4. Pour les webhooks : Developers → Webhooks → Ajouter endpoint

#### Configuration PayPal
```env
PAYPAL_MODE=sandbox
PAYPAL_CLIENT_ID=votre_client_id
PAYPAL_CLIENT_SECRET=votre_client_secret
```

**Obtenir vos identifiants PayPal** :
1. Créer un compte sur [PayPal Developer](https://developer.paypal.com)
2. Aller à Apps & Credentials
3. Sélectionner Sandbox
4. Copier Client ID et Secret

#### Configuration générale
```env
APP_NAME=MoobilPay
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Authentification
SESSION_DRIVER=database
QUEUE_CONNECTION=database

# Cache
CACHE_STORE=database
```

## 💻 Utilisation

### Démarrage du serveur de développement

```bash
# Démarrage complet (serveur + queue + logs + Vite)
composer run-script dev

# Ou démarrer individuellement :
php artisan serve                    # Serveur Laravel
php artisan queue:listen             # Queue worker
php artisan pail --timeout=0         # Logs en temps réel
npm run dev                          # Vite dev server
```

L'application sera disponible à `http://localhost:8000`

### Exécuter les tests

```bash
# Tous les tests
composer run-script test

# Tests spécifiques
php artisan test tests/Feature/PaymentTest.php

# Avec couverture de code
php artisan test --coverage
```

### Générer la documentation API

```bash
php artisan l5-swagger:generate
```

La documentation Swagger sera disponible à `/api/documentation`

## 🔐 Authentification

L'API utilise **Laravel Sanctum** pour l'authentification par token.

### Flux d'authentification

#### 1. Inscription
```http
POST /api/auth/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Réponse** :
```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "user": { "id": 1, "name": "John Doe", "email": "john@example.com" },
    "token": "1|abcdef..."
  }
}
```

#### 2. Connexion
```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "password123"
}
```

#### 3. Utilisation du token
```http
GET /api/payments
Authorization: Bearer 1|abcdef...
```

#### 4. Déconnexion
```http
POST /api/auth/logout
Authorization: Bearer 1|abcdef...
```

## 📡 API Endpoints

### Authentification

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/auth/register` | Créer un nouveau compte |
| POST | `/api/auth/login` | Se connecter |
| POST | `/api/auth/logout` | Se déconnecter |
| GET | `/api/user` | Récupérer le profil utilisateur |

### Paiements

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/payments` | Lister les paiements |
| POST | `/api/payments` | Créer un paiement |
| GET | `/api/payments/{uuid}` | Détails d'un paiement |
| POST | `/api/payments/{uuid}/confirm` | Confirmer un paiement |
| POST | `/api/payments/{uuid}/refund` | Rembourser un paiement |
| GET | `/api/payments/stats` | Statistiques des paiements |

### Méthodes de paiement

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/payment-methods` | Lister les méthodes |
| POST | `/api/payment-methods` | Ajouter une méthode |
| GET | `/api/payment-methods/{id}` | Détails d'une méthode |
| PUT | `/api/payment-methods/{id}` | Modifier une méthode |
| DELETE | `/api/payment-methods/{id}` | Supprimer une méthode |
| POST | `/api/payment-methods/{id}/set-default` | Définir par défaut |

### Flux Stripe Frontend

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/payments/stripe/frontend/intent` | Créer une intention de paiement |
| POST | `/api/payments/stripe/frontend/confirm` | Confirmer une intention |

### PayPal

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/payments/paypal/success` | Redirection après succès |
| GET | `/api/payments/paypal/cancel` | Redirection après annulation |

### Webhooks

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/webhooks/stripe` | Webhook Stripe |
| POST | `/api/webhooks/paypal` | Webhook PayPal |

## 📚 Exemples d'utilisation

### Exemple 1 : Créer un paiement Stripe

```javascript
// 1. S'authentifier
const loginRes = await fetch('/api/auth/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    email: 'user@example.com',
    password: 'password123'
  })
});
const { data: { token } } = await loginRes.json();

// 2. Créer une méthode de paiement
const methodRes = await fetch('/api/payment-methods', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    provider: 'stripe',
    payment_method_id: 'pm_card_visa',
    is_default: true
  })
});
const { data: { id: methodId } } = await methodRes.json();

// 3. Créer un paiement
const paymentRes = await fetch('/api/payments', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    provider: 'stripe',
    amount: 29.99,
    currency: 'EUR',
    description: 'Achat produit',
    payment_method_id: methodId,
    auto_confirm: true
  })
});
const { data: payment } = await paymentRes.json();
console.log('Paiement créé :', payment);
```

### Exemple 2 : Flux Stripe Payment Element

```javascript
// 1. Créer une intention de paiement
const intentRes = await fetch('/api/payments/stripe/frontend/intent', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    amount: 29.99,
    currency: 'EUR',
    description: 'Achat produit'
  })
});
const { data: { client_secret } } = await intentRes.json();

// 2. Utiliser Stripe Elements côté client
const stripe = Stripe('pk_test_...');
const elements = stripe.elements({ clientSecret: client_secret });
const paymentElement = elements.create('payment');
paymentElement.mount('#payment-element');

// 3. Confirmer le paiement
const confirmRes = await fetch('/api/payments/stripe/frontend/confirm', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    payment_intent_id: 'pi_...',
    payment_method: 'pm_...'
  })
});
```

### Exemple 3 : Flux PayPal

```javascript
// 1. Créer un paiement PayPal
const paymentRes = await fetch('/api/payments', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    provider: 'paypal',
    amount: 29.99,
    currency: 'EUR',
    description: 'Achat produit'
  })
});
const { data: { approval_url } } = await paymentRes.json();

// 2. Rediriger vers PayPal
window.location.href = approval_url;

// 3. Après retour de PayPal, confirmer le paiement
const confirmRes = await fetch(`/api/payments/${paymentId}/confirm`, {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  }
});
```

### Exemple 4 : Rembourser un paiement

```javascript
const refundRes = await fetch(`/api/payments/${paymentId}/refund`, {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    amount: 15.00,
    reason: 'Demande client'
  })
});
const { data: refund } = await refundRes.json();
console.log('Remboursement effectué :', refund);
```

## 📂 Structure du projet

```
moobilPay/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── PaymentController.php        # Gestion des paiements
│   │   │   │   ├── PaymentMethodController.php  # Gestion des méthodes
│   │   │   │   ├── StripeFrontendController.php # Flux Stripe frontend
│   │   │   │   ├── WebhookController.php        # Webhooks
│   │   │   │   ├── PayPalController.php         # Redirections PayPal
│   │   │   │   ├── AuthController.php           # Authentification
│   │   │   │   └── UserController.php           # Profil utilisateur
│   │   │   └── Controller.php                   # Contrôleur de base
│   │   └── Requests/
│   │       ├── StorePaymentRequest.php          # Validation paiements
│   │       └── StorePaymentMethodRequest.php    # Validation méthodes
│   ├── Models/
│   │   ├── User.php                             # Modèle utilisateur
│   │   ├── Payment.php                          # Modèle paiement
│   │   ├── PaymentMethod.php                    # Modèle méthode paiement
│   │   ├── Transaction.php                      # Modèle transaction
│   │   └── Webhook.php                          # Modèle webhook
│   ├── Services/
│   │   ├── PaymentService.php                   # Service principal
│   │   └── Payment/
│   │       ├── StripePaymentService.php         # Intégration Stripe
│   │       ├── PayPalPaymentService.php         # Intégration PayPal
│   │       ├── PaymentProcessor.php             # Processeur de paiement
│   │       └── WebhookHandler.php               # Gestionnaire webhooks
│   └── Providers/
│       └── AppServiceProvider.php                # Fournisseur d'app
├── routes/
│   ├── api.php                                  # Routes API
│   ├── web.php                                  # Routes web
│   └── console.php                              # Commandes console
├── database/
│   ├── migrations/                              # Migrations BD
│   ├── factories/                               # Factories de test
│   └── seeders/                                 # Seeders
├── resources/
│   ├── views/                                   # Vues Blade
│   └── js/                                      # Assets JavaScript
├── tests/
│   ├── Feature/                                 # Tests fonctionnels
│   └── Unit/                                    # Tests unitaires
├── config/
│   ├── app.php                                  # Config application
│   ├── database.php                             # Config BD
│   └── ...                                      # Autres configs
├── storage/                                     # Fichiers stockage
├── public/                                      # Fichiers publics
├── .env.example                                 # Exemple variables env
├── composer.json                                # Dépendances PHP
├── package.json                                 # Dépendances Node
├── vite.config.js                               # Config Vite
├── phpunit.xml                                  # Config PHPUnit
├── API_DOCUMENTATION.md                         # Doc API détaillée
└── README.md                                    # Ce fichier
```

## 🔒 Sécurité

### Bonnes pratiques implémentées

- **Authentification Sanctum** : Tokens sécurisés par utilisateur
- **Validation des entrées** : Form Requests pour chaque endpoint
- **Vérification des webhooks** : Signature Stripe validée
- **Chiffrement** : Données sensibles chiffrées en base
- **HTTPS obligatoire** : En production
- **CORS configuré** : Accès contrôlé
- **Rate limiting** : Protection contre les abus
- **Logging** : Audit complet des opérations

### Recommandations de sécurité

1. **Clés API** : Jamais en dur dans le code, utiliser `.env`
2. **HTTPS** : Obligatoire en production
3. **Webhooks** : Vérifier les signatures Stripe/PayPal
4. **Montants** : Valider côté serveur, jamais faire confiance au client
5. **Tokens** : Stocker de manière sécurisée côté client
6. **Logs** : Ne pas logger les données sensibles
7. **Updates** : Maintenir Laravel et les dépendances à jour

## 🧪 Tests

### Exécuter les tests

```bash
# Tous les tests
composer run-script test

# Tests spécifiques
php artisan test tests/Feature/PaymentTest.php

# Avec couverture
php artisan test --coverage

# Tests en temps réel
php artisan test --watch
```

### Structure des tests

```
tests/
├── Feature/
│   ├── PaymentTest.php              # Tests paiements
│   ├── PaymentMethodTest.php        # Tests méthodes
│   ├── AuthenticationTest.php       # Tests authentification
│   └── WebhookTest.php              # Tests webhooks
└── Unit/
    ├── PaymentServiceTest.php       # Tests service paiement
    └── StripeServiceTest.php        # Tests Stripe
```

### Exemple de test

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;

class PaymentTest extends TestCase
{
    public function test_can_create_payment()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)
            ->postJson('/api/payments', [
                'provider' => 'stripe',
                'amount' => 29.99,
                'currency' => 'EUR',
                'payment_method_id' => 1,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['uuid', 'status']]);
    }
}
```

## 🐛 Dépannage

### Problème : "SQLSTATE[08006]" - Connexion PostgreSQL échouée

**Solution** :
```bash
# Vérifier que PostgreSQL est en cours d'exécution
# Vérifier les paramètres dans .env
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=moobil_pay
DB_USERNAME=postgres
DB_PASSWORD=votre_mot_de_passe

# Créer la base de données
createdb moobil_pay

# Exécuter les migrations
php artisan migrate
```

### Problème : "Stripe API key not found"

**Solution** :
```bash
# Vérifier les clés dans .env
STRIPE_PUBLISHABLE_KEY=pk_test_...
STRIPE_SECRET_KEY=sk_test_...

# Redémarrer le serveur
php artisan serve
```

### Problème : "Unauthorized" sur les endpoints protégés

**Solution** :
```bash
# Vérifier le token d'authentification
Authorization: Bearer YOUR_TOKEN_HERE

# Générer un nouveau token
POST /api/auth/login
```

### Problème : Les migrations ne s'exécutent pas

**Solution** :
```bash
# Forcer les migrations
php artisan migrate --force

# Rollback et recommencer
php artisan migrate:rollback
php artisan migrate
```

### Problème : Webhooks Stripe ne reçoivent pas les événements

**Solution** :
1. Vérifier le `STRIPE_WEBHOOK_SECRET` dans `.env`
2. Vérifier l'URL du webhook dans Stripe Dashboard
3. Vérifier les logs : `storage/logs/laravel.log`
4. Tester avec `stripe listen` en local

```bash
# Installer Stripe CLI
# https://stripe.com/docs/stripe-cli

# Écouter les webhooks localement
stripe listen --forward-to localhost:8000/api/webhooks/stripe

# Déclencher un événement de test
stripe trigger payment_intent.succeeded
```

## 📖 Documentation supplémentaire

- **API détaillée** : Voir [API_DOCUMENTATION.md](./API_DOCUMENTATION.md)
- **Laravel** : [laravel.com/docs](https://laravel.com/docs)
- **Stripe** : [stripe.com/docs](https://stripe.com/docs)
- **PayPal** : [developer.paypal.com](https://developer.paypal.com)
- **Sanctum** : [laravel.com/docs/sanctum](https://laravel.com/docs/sanctum)

## 🤝 Contribution

Les contributions sont bienvenues ! Veuillez :

1. Fork le repository
2. Créer une branche (`git checkout -b feature/AmazingFeature`)
3. Commit vos changements (`git commit -m 'Add AmazingFeature'`)
4. Push vers la branche (`git push origin feature/AmazingFeature`)
5. Ouvrir une Pull Request

## 📝 Licence

Ce projet est sous licence MIT. Voir le fichier [LICENSE](LICENSE) pour plus de détails.

## 📞 Support

Pour toute question ou problème :

- Consulter la [documentation API](./API_DOCUMENTATION.md)
- Vérifier les [logs](storage/logs/laravel.log)
- Ouvrir une issue sur le repository
- Contacter l'équipe de développement

---

**Dernière mise à jour** : 2025
**Version** : 1.0.0
**Mainteneur** : Tchindavaldo
