<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/user",
     *     summary="Profil utilisateur",
     *     description="Récupère les informations de l'utilisateur authentifié",
     *     operationId="getCurrentUser",
     *     tags={"User"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Informations utilisateur",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="email_verified_at", type="string", format="date-time", example="2023-11-14T10:30:00Z"),
     *             @OA\Property(property="created_at", type="string", format="date-time", example="2023-11-14T10:30:00Z"),
     *             @OA\Property(property="updated_at", type="string", format="date-time", example="2023-11-14T10:30:00Z")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}
