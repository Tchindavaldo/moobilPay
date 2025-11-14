<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    private PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * @OA\Get(
     *     path="/api/payments",
     *     summary="Lister les paiements de l'utilisateur",
     *     description="Récupère la liste paginée des paiements avec filtres optionnels",
     *     operationId="getPayments",
     *     tags={"Payments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filtrer par statut du paiement",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"pending", "processing", "succeeded", "failed", "canceled", "refunded"},
     *             example="succeeded"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="provider",
     *         in="query",
     *         description="Filtrer par fournisseur de paiement",
     *         required=false,
     *         @OA\Schema(type="string", enum={"stripe", "paypal"}, example="stripe")
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Date de début (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2023-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="Date de fin (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2023-12-31")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des paiements récupérée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payments retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="uuid", type="string", example="550e8400-e29b-41d4-a716-446655440000"),
     *                     @OA\Property(property="amount", type="number", format="float", example=29.99),
     *                     @OA\Property(property="currency", type="string", example="EUR"),
     *                     @OA\Property(property="status", type="string", example="succeeded"),
     *                     @OA\Property(property="provider", type="string", example="stripe"),
     *                     @OA\Property(property="description", type="string", example="Achat produit"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2023-11-14T10:30:00Z")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $filters = $request->only(['status', 'provider', 'type', 'from_date', 'to_date']);
            
            $payments = $this->paymentService->getUserPayments($user, $filters);
            
            return response()->json([
                'success' => true,
                'data' => $payments,
                'meta' => [
                    'total' => $payments->count(),
                    'stats' => $this->paymentService->getPaymentStats($user),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payments',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/payments",
     *     summary="Créer un nouveau paiement",
     *     description="Initie un nouveau paiement via Stripe ou PayPal",
     *     operationId="createPayment",
     *     tags={"Payments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Données du paiement à créer",
     *         @OA\JsonContent(
     *             required={"provider", "amount"},
     *             @OA\Property(
     *                 property="provider",
     *                 type="string",
     *                 enum={"stripe", "paypal"},
     *                 example="stripe",
     *                 description="Fournisseur de paiement"
     *             ),
     *             @OA\Property(
     *                 property="amount",
     *                 type="number",
     *                 format="float",
     *                 example=29.99,
     *                 minimum=0.01,
     *                 description="Montant du paiement en euros"
     *             ),
     *             @OA\Property(
     *                 property="currency",
     *                 type="string",
     *                 example="EUR",
     *                 description="Code devise ISO 4217 (par défaut: EUR)"
     *             ),
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *                 example="Achat produit premium",
     *                 description="Description du paiement"
     *             ),
     *             @OA\Property(
     *                 property="payment_method_id",
     *                 type="integer",
     *                 example=1,
     *                 description="ID de la méthode de paiement enregistrée"
     *             ),
     *             @OA\Property(
     *                 property="auto_confirm",
     *                 type="boolean",
     *                 example=true,
     *                 description="Confirmer automatiquement le paiement"
     *             ),
     *             @OA\Property(
     *                 property="metadata",
     *                 type="object",
     *                 example={"order_id": "12345", "customer_id": "cust_123"},
     *                 description="Métadonnées personnalisées"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Paiement créé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payment created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="uuid", type="string", example="550e8400-e29b-41d4-a716-446655440000"),
     *                 @OA\Property(property="provider", type="string", example="stripe"),
     *                 @OA\Property(property="provider_payment_id", type="string", example="pi_1234567890"),
     *                 @OA\Property(property="amount", type="number", format="float", example=29.99),
     *                 @OA\Property(property="currency", type="string", example="EUR"),
     *                 @OA\Property(property="status", type="string", example="requires_payment_method"),
     *                 @OA\Property(property="description", type="string", example="Achat produit premium"),
     *                 @OA\Property(property="client_secret", type="string", example="pi_1234567890_secret_abc123"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2023-11-14T10:30:00Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={"amount": {"Le montant est requis."}}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur serveur",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to create payment"),
     *             @OA\Property(property="error", type="string", example="Payment provider error")
     *         )
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|in:stripe,paypal',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'sometimes|string|size:3',
            'description' => 'sometimes|string|max:255',
            'payment_method_id' => 'sometimes|exists:payment_methods,id',
            'auto_confirm' => 'sometimes|boolean',
            'metadata' => 'sometimes|array',
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
            $paymentData = $validator->validated();
            
            $payment = $this->paymentService->processPayment($user, $paymentData);
            
            return response()->json([
                'success' => true,
                'message' => 'Payment created successfully',
                'data' => $payment->load(['paymentMethod', 'transactions']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/payments/{uuid}",
     *     summary="Détails d'un paiement",
     *     tags={"Payments"},
     *     @OA\Parameter(name="uuid", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Détails du paiement")
     * )
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $user = Auth::user();
            $payment = $user->payments()
                ->where('uuid', $uuid)
                ->with(['paymentMethod', 'transactions'])
                ->firstOrFail();
            
            return response()->json([
                'success' => true,
                'data' => $payment,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found',
            ], 404);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/payments/{uuid}/confirm",
     *     summary="Confirmer un paiement",
     *     tags={"Payments"},
     *     @OA\Parameter(name="uuid", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Paiement confirmé")
     * )
     */
    public function confirm(Request $request, string $uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'confirmation_data' => 'sometimes|array',
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
            $payment = $user->payments()
                ->where('uuid', $uuid)
                ->firstOrFail();

            if (!$payment->isPending()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment cannot be confirmed',
                ], 400);
            }

            $confirmationData = $request->input('confirmation_data', []);
            $payment = $this->paymentService->confirmPayment($payment, $confirmationData);
            
            return response()->json([
                'success' => true,
                'message' => 'Payment confirmed successfully',
                'data' => $payment->load(['paymentMethod', 'transactions']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm payment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/payments/{uuid}/refund",
     *     summary="Rembourser un paiement",
     *     tags={"Payments"},
     *     @OA\Parameter(name="uuid", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Remboursement effectué")
     * )
     */
    public function refund(Request $request, string $uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'sometimes|numeric|min:0.01',
            'reason' => 'sometimes|string|max:255',
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
            $payment = $user->payments()
                ->where('uuid', $uuid)
                ->firstOrFail();

            if (!$payment->canBeRefunded()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment cannot be refunded',
                ], 400);
            }

            $amount = $request->input('amount');
            $refund = $this->paymentService->refundPayment($payment, $amount);
            
            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully',
                'data' => $refund->load(['paymentMethod', 'transactions']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process refund',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/payments/stats",
     *     summary="Statistiques des paiements",
     *     tags={"Payments"},
     *     @OA\Response(response=200, description="Statistiques des paiements")
     * )
     */
    public function stats(): JsonResponse
    {
        try {
            $user = Auth::user();
            $stats = $this->paymentService->getPaymentStats($user);
            
            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve stats',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
