<?php
// src/Controller/AuthApiController.php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\PasskeyAuthService;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/auth')]
class AuthApiController extends AbstractController
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
        private RefreshTokenGeneratorInterface $refreshGenerator,
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
    ) {}

    // ================================================
    // PARTIE 1 : INSCRIPTION VIA PASSKEY
    // ================================================

    /**
     * Étape 1 d'inscription — Génère les options à envoyer au navigateur
     * Le navigateur va créer une Passkey avec ces options
     *
     * POST /api/auth/register/options
     * Body: { "email": "user@example.com" }
     */
    #[Route('/register/options', methods: ['POST'])]
    public function registerOptions(
        Request $request,
        PasskeyAuthService $passkeyService,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $data  = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        // Vérification que l'email est fourni
        if (!$email) {
            return $this->json(
                ['error' => 'Email requis'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Cherche l'utilisateur en base — sinon en crée un nouveau
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            // Crée un nouvel utilisateur avec un mot de passe aléatoire
            // (requis par Symfony Security même si on utilise les Passkeys)
            $user = new User();
            $user->setEmail($email);
            $user->setRoles(['ROLE_USER']);

            // Mot de passe aléatoire — l'utilisateur n'en a pas besoin
            // car il se connectera uniquement via Passkey
            $randomPassword = bin2hex(random_bytes(32));
            $hashedPassword = $passwordHasher->hashPassword($user, $randomPassword);
            $user->setPassword($hashedPassword);

            $this->em->persist($user);
            $this->em->flush();
        }

        try {
            $options = $passkeyService->getRegistrationOptions($user);
            return $this->json($options);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * Étape 2 d'inscription — Vérifie la Passkey créée par le navigateur
     * Si valide, génère un JWT et un Refresh Token
     *
     * POST /api/auth/register/verify
     * Body: { "email": "user@example.com", "credential": {...} }
     */
    #[Route('/register/verify', methods: ['POST'])]
    public function registerVerify(
        Request $request,
        PasskeyAuthService $passkeyService
    ): JsonResponse {
        $data       = json_decode($request->getContent(), true);
        $email      = $data['email'] ?? null;
        $credential = $data['credential'] ?? null;

        // Vérification des données
        if (!$email || !$credential) {
            return $this->json(
                ['error' => 'Email et credential requis'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            return $this->json(
                ['error' => 'Utilisateur introuvable'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            // Vérifie et sauvegarde la Passkey
            $passkeyService->verifyRegistration($credential, $user);

            // Génère le JWT (valable 1 heure)
            $jwt = $this->jwtManager->create($user);

            // Génère le Refresh Token (valable 30 jours)
            $refreshToken = $this->refreshGenerator->createForUserWithTtl(
                $user,
                2592000 // 30 jours en secondes
            );

            return $this->json([
                'success'       => true,
                'token'         => $jwt,
                'refresh_token' => $refreshToken->getRefreshToken(),
                'user'          => [
                    'id'    => $user->getId(),
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    // ================================================
    // PARTIE 2 : CONNEXION VIA PASSKEY
    // ================================================

    /**
     * Étape 1 de connexion — Génère le challenge pour le navigateur
     *
     * POST /api/auth/login/options
     */
    #[Route('/login/options', methods: ['POST'])]
    public function loginOptions(PasskeyAuthService $passkeyService): JsonResponse
    {
        try {
            return $this->json($passkeyService->getLoginOptions());
        } catch (\Exception $e) {
            return $this->json(
                ['error' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * Étape 2 de connexion — Vérifie la signature de la Passkey
     * Si valide, génère un JWT et un Refresh Token
     *
     * POST /api/auth/login/verify
     * Body: { "credential": {...} }
     */
    #[Route('/login/verify', methods: ['POST'])]
    public function loginVerify(
        Request $request,
        PasskeyAuthService $passkeyService
    ): JsonResponse {
        $data       = json_decode($request->getContent(), true);
        $credential = $data['credential'] ?? null;

        if (!$credential) {
            return $this->json(
                ['error' => 'Credential requis'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            // Vérifie la Passkey et retourne l'utilisateur
            $user = $passkeyService->verifyLogin($credential);

            // Génère le JWT
            $jwt = $this->jwtManager->create($user);

            // Génère le Refresh Token
            $refreshToken = $this->refreshGenerator->createForUserWithTtl(
                $user,
                2592000
            );

            return $this->json([
                'success'       => true,
                'token'         => $jwt,
                'refresh_token' => $refreshToken->getRefreshToken(),
                'user'          => [
                    'id'    => $user->getId(),
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    // ================================================
    // PARTIE 3 : INFORMATIONS UTILISATEUR CONNECTÉ
    // ================================================

    /**
     * Retourne les infos de l'utilisateur connecté
     * Nécessite un JWT valide dans le header Authorization
     *
     * GET /api/auth/me
     * Header: Authorization: Bearer <token>
     */
    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(
                ['error' => 'Non authentifié'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        return $this->json([
            'id'    => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]);
    }
}