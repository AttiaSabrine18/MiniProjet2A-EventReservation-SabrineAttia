<?php
// src/Service/EmailVerificationService.php

namespace App\Service;

use App\Entity\EmailVerification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class EmailVerificationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private EmailService $emailService
    ) {}

    /**
     * Crée un token de vérification et envoie l'email
     */
    public function createVerification(User $user): EmailVerification
    {
        // Désactiver les anciens tokens non utilisés
        foreach ($user->getEmailVerifications() as $oldVerification) {
            if (!$oldVerification->isUsed() && $oldVerification->isValid()) {
                $oldVerification->setIsUsed(true);
            }
        }

        // Créer un nouveau token
        $verification = new EmailVerification();
        $verification->setUser($user);
        
        $this->em->persist($verification);
        $this->em->flush();

        // Envoyer l'email
        $this->emailService->sendVerificationEmail($verification);

        return $verification;
    }

    /**
     * Vérifie un token et active le compte
     */
    public function verifyEmail(string $token): ?User
    {
        $verification = $this->em->getRepository(EmailVerification::class)
            ->findOneBy(['token' => $token]);

        if (!$verification) {
            return null;
        }

        if (!$verification->isValid()) {
            return null;
        }

        $user = $verification->getUser();
        
        // Marquer le token comme utilisé
        $verification->setIsUsed(true);
        
        // Marquer l'utilisateur comme vérifié
        $user->setIsVerified(true);
        
        $this->em->flush();

        // Envoyer l'email de bienvenue
        $this->emailService->sendWelcomeEmail($user);

        return $user;
    }

    /**
     * Renvoie un email de vérification
     */
    public function resendVerification(User $user): void
    {
        if (!$user->isVerified()) {
            $this->createVerification($user);
        }
    }
}