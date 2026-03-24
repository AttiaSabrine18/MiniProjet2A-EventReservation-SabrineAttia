<?php
// src/Controller/VerificationController.php

namespace App\Controller;

use App\Service\EmailVerificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class VerificationController extends AbstractController
{
    #[Route('/verify-email/{token}', name: 'app_verify_email')]
    public function verifyEmail(
        string $token, 
        EmailVerificationService $verificationService,
        \Symfony\Component\HttpFoundation\Request $request,
        \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface $tokenStorage,
        \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
    ): Response {
        error_log('=== VerificationController: Tentative de vérification avec token: ' . substr($token, 0, 20) . '... ===');
        
        $user = $verificationService->verifyEmail($token);

        if (!$user) {
            error_log('=== VerificationController: Échec de vérification ===');
            $this->addFlash('error', 'Lien de vérification invalide ou expiré.');
            return $this->redirectToRoute('app_login');
        }

        error_log('=== VerificationController: Succès - Utilisateur vérifié: ' . $user->getEmail() . ' ===');
        
        // CONNECTER AUTOMATIQUEMENT L'UTILISATEUR APRÈS VÉRIFICATION
        $token = new UsernamePasswordToken(
            $user,
            'main', // Nom du firewall
            $user->getRoles()
        );
        
        $tokenStorage->setToken($token);
        
        // Stocker dans la session
        $session = $request->getSession();
        $session->set('_security_main', serialize($token));
        
        // Déclencher l'événement de login
        $event = new InteractiveLoginEvent($request, $token);
        $eventDispatcher->dispatch($event, 'security.interactive_login');
        
        $this->addFlash('success', 'Votre email a été vérifié avec succès ! Vous êtes maintenant connecté.');

        return $this->redirectToRoute('app_home');
    }

    #[Route('/resend-verification', name: 'app_resend_verification', methods: ['POST'])]
    public function resendVerification(
        \Symfony\Component\HttpFoundation\Request $request,
        EmailVerificationService $verificationService,
        \App\Repository\UserRepository $userRepository
    ): Response {
        $email = $request->get('email');
        
        if (!$email) {
            $this->addFlash('error', 'Email requis.');
            return $this->redirectToRoute('app_login');
        }

        $user = $userRepository->findOneBy(['email' => $email]);

        if ($user && !$user->isVerified()) {
            $verificationService->resendVerification($user);
            $this->addFlash('success', 'Un nouvel email de vérification a été envoyé.');
        } else {
            $this->addFlash('info', 'Si votre compte n\'est pas encore vérifié, vous recevrez un email.');
        }

        return $this->redirectToRoute('app_login');
    }
}