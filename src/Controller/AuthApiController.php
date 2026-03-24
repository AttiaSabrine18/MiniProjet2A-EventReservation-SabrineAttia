<?php
// src/Controller/AuthApiController.php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\SimplePasskeyService;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

#[Route('/api/auth')]
class AuthApiController extends AbstractController
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
        private RefreshTokenGeneratorInterface $refreshGenerator,
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private TokenStorageInterface $tokenStorage,
        private EventDispatcherInterface $eventDispatcher,
        private EmailVerificationService $emailVerificationService,
    ) {}

    /**
     * Méthode utilitaire pour créer la session Symfony
     */
    private function createSymfonySession(Request $request, User $user): void
    {
        // Créer un token d'authentification
        $token = new UsernamePasswordToken(
            $user,
            'main',
            $user->getRoles()
        );
        
        // Stocker le token dans le token storage
        $this->tokenStorage->setToken($token);
        
        // Stocker le token dans la session
        $session = $request->getSession();
        $session->set('_security_main', serialize($token));
        
        // Déclencher l'événement de login
        $event = new InteractiveLoginEvent($request, $token);
        $this->eventDispatcher->dispatch($event, 'security.interactive_login');
    }

    // ================================================
    // AUTHENTIFICATION SIMPLE (EMAIL/MOT DE PASSE)
    // ================================================

    #[Route('/register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return $this->json(['error' => 'Email et mot de passe requis'], Response::HTTP_BAD_REQUEST);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Email invalide'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($password) < 8) {
            return $this->json(['error' => 'Le mot de passe doit contenir au moins 8 caractères'], Response::HTTP_BAD_REQUEST);
        }

        $existingUser = $this->userRepository->findOneBy(['email' => $email]);
        if ($existingUser) {
            return $this->json(['error' => 'Cet email est déjà utilisé'], Response::HTTP_CONFLICT);
        }

        try {
            $user = new User();
            $user->setEmail($email);
            $user->setRoles(['ROLE_USER']);
            $user->setIsVerified(false);
            
            $hashedPassword = $passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);

            $this->em->persist($user);
            $this->em->flush();

            // Envoyer l'email de vérification
            $this->emailVerificationService->createVerification($user);

            return $this->json([
                'success' => true,
                'message' => 'Inscription réussie ! Un email de vérification vous a été envoyé.',
                'requires_verification' => true,
                'email' => $user->getEmail()
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de l\'inscription'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/login', methods: ['POST'])]
    public function login(
        Request $request,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return $this->json(['error' => 'Email et mot de passe requis'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        
        if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'Email ou mot de passe incorrect'], Response::HTTP_UNAUTHORIZED);
        }

        // Vérifier si l'email est confirmé
        if (!$user->isVerified()) {
            return $this->json([
                'error' => 'Veuillez vérifier votre email avant de vous connecter.',
                'requires_verification' => true,
                'email' => $user->getEmail()
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Créer la session Symfony
        $this->createSymfonySession($request, $user);

        $jwt = $this->jwtManager->create($user);
        $refreshToken = $this->refreshGenerator->createForUserWithTtl($user, 2592000);

        return $this->json([
            'success' => true,
            'token' => $jwt,
            'refresh_token' => $refreshToken->getRefreshToken(),
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ]
        ]);
    }

    // ================================================
    // INSCRIPTION PASSKEY
    // ================================================

    #[Route('/register/options', methods: ['POST'])]
    public function registerOptions(
        Request $request,
        SimplePasskeyService $passkeyService,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return $this->json(['error' => 'Email requis'], Response::HTTP_BAD_REQUEST);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Email invalide'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $this->userRepository->findOneBy(['email' => $email]);

            if (!$user) {
                $user = new User();
                $user->setEmail($email);
                $user->setRoles(['ROLE_USER']);
                $user->setIsVerified(false);

                $randomPassword = bin2hex(random_bytes(32));
                $hashedPassword = $passwordHasher->hashPassword($user, $randomPassword);
                $user->setPassword($hashedPassword);

                $this->em->persist($user);
                $this->em->flush();
            }

            $options = $passkeyService->getRegistrationOptions($user);
            return $this->json($options);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/register/verify', methods: ['POST'])]
    public function registerVerify(
        Request $request,
        SimplePasskeyService $passkeyService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $credential = $data['credential'] ?? null;

        if (!$email || !$credential) {
            return $this->json(['error' => 'Email et credential requis'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $passkeyService->verifyRegistration($credential, $user);

            // Envoyer l'email de vérification
            $this->emailVerificationService->createVerification($user);

            return $this->json([
                'success' => true,
                'message' => 'Inscription réussie ! Un email de vérification vous a été envoyé.',
                'requires_verification' => true,
                'email' => $user->getEmail()
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    // ================================================
    // CONNEXION PASSKEY
    // ================================================

    #[Route('/login/options', methods: ['POST'])]
    public function loginOptions(SimplePasskeyService $passkeyService): JsonResponse
    {
        try {
            $options = $passkeyService->getLoginOptions();
            return $this->json($options);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

 #[Route('/login/verify', methods: ['POST'])]
public function loginVerify(
    Request $request,
    SimplePasskeyService $passkeyService
): JsonResponse {
    $data = json_decode($request->getContent(), true);
    $credential = $data['credential'] ?? null;

    if (!$credential) {
        return $this->json(['error' => 'Credential requis'], Response::HTTP_BAD_REQUEST);
    }

    try {
        $user = $passkeyService->verifyLogin($credential);

        if (!$user) {
            return $this->json(['error' => 'Utilisateur non trouvé'], Response::HTTP_UNAUTHORIZED);
        }

        // Vérifier si l'email est confirmé
        if (!$user->isVerified()) {
            return $this->json([
                'error' => 'Veuillez vérifier votre email avant de vous connecter.',
                'requires_verification' => true,
                'email' => $user->getEmail()
            ], Response::HTTP_UNAUTHORIZED);
        }

        // CRÉER LA SESSION SYMFONY AVEC LE BON UTILISATEUR
        $this->createSymfonySession($request, $user);

        $jwt = $this->jwtManager->create($user);
        $refreshToken = $this->refreshGenerator->createForUserWithTtl($user, 2592000);

        return $this->json([
            'success' => true,
            'token' => $jwt,
            'refresh_token' => $refreshToken->getRefreshToken(),
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ]
        ]);
    } catch (\Exception $e) {
        return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
    }
}

    // ================================================
    // INFORMATIONS UTILISATEUR
    // ================================================

    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]);
    }
}