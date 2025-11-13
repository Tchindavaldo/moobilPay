<?php

use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\WebhookController;
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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

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
    Route::get('/success', function (Request $request) {
        return response()->json([
            'success' => true,
            'message' => 'Payment approved successfully',
            'data' => [
                'order_id' => $request->query('token'),
                'payer_id' => $request->query('PayerID'),
            ],
        ]);
    })->name('payments.paypal.success');

    Route::get('/cancel', function (Request $request) {
        return response()->json([
            'success' => false,
            'message' => 'Payment was cancelled',
            'data' => [
                'order_id' => $request->query('token'),
            ],
        ]);
    })->name('payments.paypal.cancel');
});
