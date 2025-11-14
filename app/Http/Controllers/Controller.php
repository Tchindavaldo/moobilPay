<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="MoobilPay API",
 *     version="1.0.0",
 *     description="API complète de paiement en ligne avec Stripe et PayPal. Permet de gérer les paiements, méthodes de paiement et webhooks de manière sécurisée.",
 *     @OA\Contact(
 *         email="tchindavaldoblair@gmail.com",
 *         name="Support MoobilPay"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Serveur de développement local"
 * )
 * 
 * @OA\Server(
 *     url="https://api.moobilpay.com",
 *     description="Serveur de production"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Authentification via Laravel Sanctum. Utilisez votre token d'API."
 * )
 * 
 * @OA\Tag(
 *     name="Payments",
 *     description="Gestion des paiements Stripe et PayPal - création, confirmation, remboursement"
 * )
 * 
 * @OA\Tag(
 *     name="Payment Methods",
 *     description="Gestion des méthodes de paiement - cartes, PayPal, etc."
 * )
 * 
 * @OA\Tag(
 *     name="Webhooks",
 *     description="Réception des événements de paiement des fournisseurs"
 * )
 * 
 * @OA\Tag(
 *     name="User",
 *     description="Gestion du profil utilisateur"
 * )
 * 
 * @OA\Tag(
 *     name="Authentication",
 *     description="Inscription, connexion et déconnexion des utilisateurs"
 * )
 */
abstract class Controller
{
    //
}
