<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PayPalController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/payments/paypal/success",
     *     summary="Retour PayPal - Paiement approuvé",
     *     description="URL de retour après approbation du paiement PayPal",
     *     tags={"Payments"},
     *     @OA\Parameter(name="token", in="query", required=true, @OA\Schema(type="string"), description="Token PayPal"),
     *     @OA\Parameter(name="PayerID", in="query", required=true, @OA\Schema(type="string"), description="ID du payeur PayPal"),
     *     @OA\Response(
     *         response=200,
     *         description="Paiement approuvé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payment approved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="order_id", type="string", example="PAYID-123456"),
     *                 @OA\Property(property="payer_id", type="string", example="PAYER123")
     *             )
     *         )
     *     )
     * )
     */
    public function success(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Payment approved successfully',
            'data' => [
                'order_id' => $request->query('token'),
                'payer_id' => $request->query('PayerID'),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/payments/paypal/cancel",
     *     summary="Retour PayPal - Paiement annulé",
     *     description="URL de retour après annulation du paiement PayPal",
     *     tags={"Payments"},
     *     @OA\Parameter(name="token", in="query", required=true, @OA\Schema(type="string"), description="Token PayPal"),
     *     @OA\Response(
     *         response=200,
     *         description="Paiement annulé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Payment was cancelled"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="order_id", type="string", example="PAYID-123456")
     *             )
     *         )
     *     )
     * )
     */
    public function cancel(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Payment was cancelled',
            'data' => [
                'order_id' => $request->query('token'),
            ],
        ]);
    }
}
