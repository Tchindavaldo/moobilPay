<?php

use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\PayPalController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StripeFrontendController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Routes d'authentification (publiques)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
});

Route::middleware('auth:sanctum')->get('/user', [UserController::class, 'show']);

// Routes publiques (webhooks)
Route::prefix('webhooks')->group(function () {
    Route::post('/stripe', [WebhookController::class, 'stripe'])->name('webhooks.stripe');
    Route::post('/paypal', [WebhookController::class, 'paypal'])->name('webhooks.paypal');
});

// Routes protégées par authentification
Route::middleware('auth:sanctum')->group(function () {
    
    // Routes des paiements
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('/', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('/stats', [PaymentController::class, 'stats'])->name('payments.stats');
        Route::get('/{uuid}', [PaymentController::class, 'show'])->name('payments.show');
        Route::post('/{uuid}/confirm', [PaymentController::class, 'confirm'])->name('payments.confirm');
        Route::post('/{uuid}/refund', [PaymentController::class, 'refund'])->name('payments.refund');
    });

    // Flux frontend Stripe (Payment Element)
    Route::prefix('payments/stripe/frontend')->group(function () {
        Route::post('/intent', [StripeFrontendController::class, 'createIntent'])->name('payments.stripe.frontend.intent');
        Route::post('/confirm', [StripeFrontendController::class, 'confirmIntent'])->name('payments.stripe.frontend.confirm');
    });

    // Routes des méthodes de paiement
    Route::prefix('payment-methods')->group(function () {
        Route::get('/', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
        Route::post('/', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
        Route::get('/{id}', [PaymentMethodController::class, 'show'])->name('payment-methods.show');
        Route::put('/{id}', [PaymentMethodController::class, 'update'])->name('payment-methods.update');
        Route::delete('/{id}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');
        Route::post('/{id}/set-default', [PaymentMethodController::class, 'setDefault'])->name('payment-methods.set-default');
    });
});

// Routes spécifiques pour les redirections PayPal
Route::prefix('payments/paypal')->group(function () {
    Route::get('/success', [PayPalController::class, 'success'])->name('payments.paypal.success');
    Route::get('/cancel', [PayPalController::class, 'cancel'])->name('payments.paypal.cancel');
});
