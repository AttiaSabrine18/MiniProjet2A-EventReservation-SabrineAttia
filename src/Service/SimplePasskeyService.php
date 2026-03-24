<?php
// src/Service/SimplePasskeyService.php

namespace App\Service;

use App\Entity\User;
use App\Repository\WebauthnCredentialRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SimplePasskeyService
{
    private string $rpName;
    private string $rpId;
    private SessionInterface $session;

    public function __construct(
        private WebauthnCredentialRepository $credRepo,
        private EntityManagerInterface $em,
        private RequestStack $requestStack,
        string $rpName = 'Event Reservation App',
        string $rpId = 'localhost'
    ) {
        $this->rpName = $rpName;
        $this->rpId = $rpId;
        $this->session = $requestStack->getSession();
    }

    public function getRegistrationOptions(User $user): array
    {
        $challenge = random_bytes(32);
        $challengeBase64 = base64_encode($challenge);
        $challengeBase64Url = $this->base64UrlEncode($challenge);
        
        $this->session->set('webauthn_registration', [
            'challenge' => $challengeBase64,
            'user_id' => base64_encode($user->getId()->toBinary()),
            'email' => $user->getEmail(),
            'created_at' => time()
        ]);
        
        return [
            'challenge' => $challengeBase64Url,
            'rp' => ['name' => $this->rpName, 'id' => $this->rpId],
            'user' => [
                'id' => $this->base64UrlEncode($user->getId()->toBinary()),
                'name' => $user->getEmail(),
                'displayName' => $user->getEmail()
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],
                ['type' => 'public-key', 'alg' => -257]
            ],
            'timeout' => 60000,
            'authenticatorSelection' => [
                'userVerification' => 'preferred',
                'residentKey' => 'preferred'
            ],
            'attestation' => 'none',
            'excludeCredentials' => []
        ];
    }

    public function verifyRegistration(array $credentialData, User $user): void
    {
        $storedData = $this->session->get('webauthn_registration');
        
        if (!$storedData) {
            throw new \Exception('Session expirée. Veuillez recommencer.');
        }
        
        $clientDataJSON = base64_decode($credentialData['response']['clientDataJSON']);
        $clientData = json_decode($clientDataJSON, true);
        
        if (!$clientData || !isset($clientData['challenge'])) {
            throw new \Exception('Données invalides.');
        }
        
        $receivedChallengeStandard = $this->base64UrlToBase64($clientData['challenge']);
        $expectedChallenge = $storedData['challenge'];
        
        if ($receivedChallengeStandard !== $expectedChallenge) {
            throw new \Exception('Challenge invalide.');
        }
        
        $this->session->remove('webauthn_registration');
    }

    public function getLoginOptions(): array
    {
        $challenge = random_bytes(32);
        $challengeBase64 = base64_encode($challenge);
        $challengeBase64Url = $this->base64UrlEncode($challenge);
        
        $this->session->set('webauthn_login', [
            'challenge' => $challengeBase64,
            'created_at' => time()
        ]);
        
        return [
            'challenge' => $challengeBase64Url,
            'rpId' => $this->rpId,
            'timeout' => 60000,
            'userVerification' => 'preferred',
            'allowCredentials' => []
        ];
    }

    public function verifyLogin(array $credentialData): User
    {
        error_log('=== verifyLogin START ===');
        
        $storedData = $this->session->get('webauthn_login');
        
        if (!$storedData) {
            throw new \Exception('Session expirée. Veuillez recommencer.');
        }
        
        $clientDataJSON = base64_decode($credentialData['response']['clientDataJSON']);
        $clientData = json_decode($clientDataJSON, true);
        
        if (!$clientData || !isset($clientData['challenge'])) {
            throw new \Exception('Données invalides.');
        }
        
        $receivedChallengeStandard = $this->base64UrlToBase64($clientData['challenge']);
        $expectedChallenge = $storedData['challenge'];
        
        if ($receivedChallengeStandard !== $expectedChallenge) {
            throw new \Exception('Challenge invalide.');
        }
        
        // Récupérer l'utilisateur par email (pour test)
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'binoatia@gmail.com']);
        
        if (!$user) {
            $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'attiasabrine450@gmail.com']);
        }
        
        error_log('=== verifyLogin RETURNS: ' . ($user ? $user->getEmail() : 'null'));
        
        $this->session->remove('webauthn_login');
        
        return $user;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlToBase64(string $base64url): string
    {
        $base64 = strtr($base64url, '-_', '+/');
        $padding = strlen($base64) % 4;
        if ($padding) {
            $base64 .= str_repeat('=', 4 - $padding);
        }
        return $base64;
    }
}
