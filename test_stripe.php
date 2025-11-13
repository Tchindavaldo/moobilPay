<?php

require_once 'vendor/autoload.php';

// Charger les variables d'environnement
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Configuration Stripe depuis les variables d'environnement
$stripeKey = $_ENV['STRIPE_SECRET_KEY'] ?? 'sk_test_YOUR_KEY_HERE';
$stripe = new \Stripe\StripeClient($stripeKey);

try {
    echo "🔍 Test de connexion à Stripe...\n\n";
    
    // Test 1: Récupérer les informations du compte
    echo "1. Test de récupération du compte:\n";
    $account = $stripe->accounts->retrieve();
    echo "✅ Connexion réussie!\n";
    echo "   Nom du compte: " . ($account->business_profile->name ?? 'Non défini') . "\n";
    echo "   Email: " . $account->email . "\n";
    echo "   Pays: " . $account->country . "\n\n";
    
    // Test 2: Créer un customer de test
    echo "2. Test de création d'un customer:\n";
    $customer = $stripe->customers->create([
        'email' => 'test@example.com',
        'name' => 'Test Customer',
        'description' => 'Customer de test créé via API',
    ]);
    echo "✅ Customer créé avec succès!\n";
    echo "   ID: " . $customer->id . "\n";
    echo "   Email: " . $customer->email . "\n\n";
    
    // Test 3: Créer un Payment Intent de test
    echo "3. Test de création d'un Payment Intent:\n";
    $paymentIntent = $stripe->paymentIntents->create([
        'amount' => 2000, // 20.00 EUR en centimes
        'currency' => 'eur',
        'customer' => $customer->id,
        'description' => 'Test payment via API',
        'metadata' => [
            'test' => 'true',
            'source' => 'api_test'
        ]
    ]);
    echo "✅ Payment Intent créé avec succès!\n";
    echo "   ID: " . $paymentIntent->id . "\n";
    echo "   Montant: " . ($paymentIntent->amount / 100) . " " . strtoupper($paymentIntent->currency) . "\n";
    echo "   Status: " . $paymentIntent->status . "\n";
    echo "   Client Secret: " . $paymentIntent->client_secret . "\n\n";
    
    // Test 4: Lister les derniers Payment Intents
    echo "4. Test de récupération des derniers Payment Intents:\n";
    $paymentIntents = $stripe->paymentIntents->all(['limit' => 3]);
    echo "✅ Récupération réussie!\n";
    echo "   Nombre de Payment Intents trouvés: " . count($paymentIntents->data) . "\n";
    
    foreach ($paymentIntents->data as $pi) {
        echo "   - " . $pi->id . " | " . ($pi->amount / 100) . " " . strtoupper($pi->currency) . " | " . $pi->status . "\n";
    }
    
    echo "\n🎉 Tous les tests Stripe ont réussi!\n";
    echo "Votre configuration Stripe fonctionne parfaitement.\n";
    
} catch (\Stripe\Exception\AuthenticationException $e) {
    echo "❌ Erreur d'authentification Stripe:\n";
    echo "   Message: " . $e->getMessage() . "\n";
    echo "   Vérifiez votre clé API Stripe dans le script.\n";
} catch (\Stripe\Exception\ApiErrorException $e) {
    echo "❌ Erreur API Stripe:\n";
    echo "   Message: " . $e->getMessage() . "\n";
    echo "   Code: " . $e->getStripeCode() . "\n";
} catch (Exception $e) {
    echo "❌ Erreur générale:\n";
    echo "   Message: " . $e->getMessage() . "\n";
}
