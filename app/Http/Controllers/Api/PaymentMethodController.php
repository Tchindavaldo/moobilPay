<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PaymentMethodController extends Controller
{
    private PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * @OA\Get(
     *     path="/api/payment-methods",
     *     summary="Lister les méthodes de paiement",
     *     tags={"Payment Methods"},
     *     @OA\Response(response=200, description="Liste des méthodes de paiement")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $provider = $request->query('provider');
            
            $paymentMethods = $this->paymentService->getUserPaymentMethods($user, $provider);
            
            return response()->json([
                'success' => true,
                'data' => $paymentMethods,
                'meta' => [
                    'total' => $paymentMethods->count(),
                    'default_method' => $user->defaultPaymentMethod(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment methods',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/payment-methods",
     *     summary="Créer une méthode de paiement",
     *     tags={"Payment Methods"},
     *     @OA\Response(response=201, description="Méthode de paiement créée")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|in:stripe,paypal',
            'payment_method_id' => 'required_if:provider,stripe|string',
            'payer_id' => 'required_if:provider,paypal|string',
            'email' => 'required_if:provider,paypal|email',
            'is_default' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = Auth::user();
            $provider = $request->input('provider');
            $paymentData = $validator->validated();
            
            $paymentMethod = $this->paymentService->createPaymentMethod($user, $provider, $paymentData);
            
            return response()->json([
                'success' => true,
                'message' => 'Payment method created successfully',
                'data' => $paymentMethod,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment method',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/payment-methods/{id}",
     *     summary="Détails d'une méthode de paiement",
     *     tags={"Payment Methods"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"), description="ID de la méthode de paiement"),
     *     @OA\Response(response=200, description="Détails de la méthode de paiement"),
     *     @OA\Response(response=404, description="Méthode de paiement non trouvée")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $paymentMethod = $user->paymentMethods()
                ->where('id', $id)
                ->firstOrFail();
            
            return response()->json([
                'success' => true,
                'data' => $paymentMethod,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method not found',
            ], 404);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/payment-methods/{id}",
     *     summary="Modifier une méthode de paiement",
     *     tags={"Payment Methods"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="is_default", type="boolean", example=true),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Méthode de paiement modifiée")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'is_default' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = Auth::user();
            $paymentMethod = $user->paymentMethods()
                ->where('id', $id)
                ->firstOrFail();

            // Si on définit comme défaut
            if ($request->has('is_default') && $request->input('is_default')) {
                $this->paymentService->setDefaultPaymentMethod($paymentMethod);
            } else {
                $paymentMethod->update($validator->validated());
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Payment method updated successfully',
                'data' => $paymentMethod->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment method',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/payment-methods/{id}",
     *     summary="Supprimer une méthode de paiement",
     *     tags={"Payment Methods"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Méthode de paiement supprimée"),
     *     @OA\Response(response=404, description="Méthode de paiement non trouvée")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $paymentMethod = $user->paymentMethods()
                ->where('id', $id)
                ->firstOrFail();

            $success = $this->paymentService->deletePaymentMethod($paymentMethod);
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment method deleted successfully',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete payment method',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method not found',
            ], 404);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/payment-methods/{id}/set-default",
     *     summary="Définir comme méthode de paiement par défaut",
     *     tags={"Payment Methods"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Méthode définie par défaut"),
     *     @OA\Response(response=404, description="Méthode de paiement non trouvée")
     * )
     */
    public function setDefault(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $paymentMethod = $user->paymentMethods()
                ->where('id', $id)
                ->active()
                ->firstOrFail();

            $success = $this->paymentService->setDefaultPaymentMethod($paymentMethod);
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Default payment method updated successfully',
                    'data' => $paymentMethod->fresh(),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to set default payment method',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method not found',
            ], 404);
        }
    }
}
