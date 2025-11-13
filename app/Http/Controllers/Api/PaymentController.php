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
