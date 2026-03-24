<?php
// src/Service/EmailService.php

namespace App\Service;

use App\Entity\EmailVerification;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private string $fromEmail = 'noreply@eventreservation.com'
    ) {
    }

    /**
     * Envoie un email de vérification
     */
    public function sendVerificationEmail(EmailVerification $verification): void
    {
        $user = $verification->getUser();
        
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, 'Event Reservation'))
            ->to(new Address($user->getEmail()))
            ->subject('Confirmez votre inscription')
            ->htmlTemplate('emails/verification.html.twig')
            ->context([
                'user' => $user,
                'verification' => $verification,
                'token' => $verification->getToken(),
            ]);

        $this->mailer->send($email);
    }

    /**
     * Envoie un email de bienvenue
     */
    public function sendWelcomeEmail(User $user): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, 'Event Reservation'))
            ->to(new Address($user->getEmail()))
            ->subject('Bienvenue sur Event Reservation !')
            ->htmlTemplate('emails/welcome.html.twig')
            ->context([
                'user' => $user,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Envoie une confirmation de réservation
     */
    public function sendReservationConfirmation(User $user, array $reservation): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, 'Event Reservation'))
            ->to(new Address($user->getEmail()))
            ->subject('Confirmation de votre réservation')
            ->htmlTemplate('emails/reservation_confirmation.html.twig')
            ->context([
                'user' => $user,
                'reservation' => $reservation,
            ]);

        $this->mailer->send($email);
    }
}