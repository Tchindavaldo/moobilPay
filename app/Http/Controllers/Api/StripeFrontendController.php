<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payment\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StripeFrontendController extends Controller
{
    private StripePaymentService $stripeService;

    public function __construct(StripePaymentService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * @OA\Post(
     *     path="/api/payments/stripe/frontend/intent",
     *     summary="Créer un PaymentIntent (flux frontend)",
     *     tags={"Payments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount"},
     *             @OA\Property(property="amount", type="number", example=29.99),
     *             @OA\Property(property="currency", type="string", example="EUR"),
     *             @OA\Property(property="metadata", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Client secret retourné",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="payment_intent_id", type="string"),
     *             @OA\Property(property="client_secret", type="string"),
     *             @OA\Property(property="status", type="string")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation failed")
     * )
     */
    public function createIntent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'sometimes|string|size:3',
            'metadata' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->stripeService->createFrontendPaymentIntent($validator->validated());

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create PaymentIntent',
                'error' => $result['error'] ?? null,
            ], 500);
        }

        return response()->json($result);
    }

    /**
     * @OA\Post(
     *     path="/api/payments/stripe/frontend/confirm",
     *     summary="Confirmer un PaymentIntent (flux frontend)",
     *     tags={"Payments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"payment_intent_id"},
     *             @OA\Property(property="payment_intent_id", type="string", example="pi_123"),
     *             @OA\Property(property="payment_method", type="string", example="pm_123" )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Statut du PaymentIntent")
     * )
     */
    public function confirmIntent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_intent_id' => 'required|string',
            'payment_method' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $result = $this->stripeService->confirmFrontendPaymentIntent($data['payment_intent_id'], $data);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm PaymentIntent',
                'error' => $result['error'] ?? null,
            ], 500);
        }

        return response()->json($result);
    }
}
