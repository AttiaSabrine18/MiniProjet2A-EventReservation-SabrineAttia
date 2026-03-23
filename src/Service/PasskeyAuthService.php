<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\WebauthnCredentialRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

class PasskeyAuthService
{
    private string $rpName;
    private string $rpId;

public function __construct(
    private WebauthnCredentialRepository $credRepo,
    private EntityManagerInterface $em,
    private RequestStack $requestStack,
    private SerializerInterface $serializer,  
    string $rpName = 'Mon Application',      
    string $rpId = 'localhost'
) {
    $this->rpName = $rpName;
    $this->rpId   = $rpId;
}

    // ================================================
    // MÉTHODE UTILITAIRE PRIVÉE
    // ================================================

    /**
     * Convertit un objet WebAuthn en tableau PHP
     * Utilise le Serializer Symfony qui gère les types complexes
     */
    private function toArray(object $options): array
    {
        // Sérialise en JSON puis décode en tableau
        $json = $this->serializer->serialize($options, 'json');
        $result = json_decode($json, true);

        // Si le serializer échoue, on essaie manuellement
        if ($result === null) {
            throw new \RuntimeException(
                'Impossible de sérialiser les options WebAuthn : ' . json_last_error_msg()
            );
        }

        return $result;
    }

    // ================================================
    // PARTIE 1 : ENREGISTREMENT D'UNE NOUVELLE PASSKEY
    // ================================================

    /**
     * Génère les options à envoyer au navigateur pour créer une Passkey
     */
    public function getRegistrationOptions(User $user): array
    {
        // Qui est le site (Relying Party = ton application)
        $rp = PublicKeyCredentialRpEntity::create(
            $this->rpName,
            $this->rpId
        );

        // Qui est l'utilisateur qui s'enregistre
        $userEntity = PublicKeyCredentialUserEntity::create(
            $user->getEmail(),
            $user->getId()->toBinary(),
            $user->getEmail()
        );

        // Algorithmes cryptographiques acceptés
        $pubKeyCredParams = [
            PublicKeyCredentialParameters::create('public-key', -7),   // ES256
            PublicKeyCredentialParameters::create('public-key', -257), // RS256
        ];

        // Challenge aléatoire — empêche les attaques replay
        $challenge = random_bytes(32);

        // Crée les options complètes
        $options = PublicKeyCredentialCreationOptions::create(
            rp: $rp,
            user: $userEntity,
            challenge: $challenge,
            pubKeyCredParams: $pubKeyCredParams,
        );

        // Sauvegarde en session pour vérifier plus tard
        $session = $this->requestStack->getSession();
        $session->set('webauthn_registration', serialize($options));

        return $this->toArray($options);
    }

    /**
     * Valide l'enregistrement et lie la passkey à l'utilisateur
     */
    public function verifyRegistration(array $credentialData, User $user): void
    {
        $session = $this->requestStack->getSession();
        $options = unserialize($session->get('webauthn_registration'));

        // Configure le gestionnaire d'attestation
        $attestationManager = AttestationStatementSupportManager::create();
        $attestationManager->add(NoneAttestationStatementSupport::create());

        $factory = new CeremonyStepManagerFactory();
        $factory->setAttestationStatementSupportManager($attestationManager);

        // Crée le validateur
        $validator = AuthenticatorAttestationResponseValidator::create(
            $factory->requestCreation()
        );

        // Décode la réponse du navigateur
        $publicKeyCredential = PublicKeyCredential::createFromArray($credentialData);

        if (!$publicKeyCredential->response instanceof AuthenticatorAttestationResponse) {
            throw new \Exception('Réponse invalide — type attendu : attestation');
        }

        // Vérifie et valide la réponse
        $source = $validator->check(
            $publicKeyCredential->response,
            $options,
            $this->rpId,
            ['https://' . $this->rpId, 'http://' . $this->rpId]
        );

        // Sauvegarde en base via le repository
        $this->credRepo->saveCredential($user, $source);
        $session->remove('webauthn_registration');
    }

    // ================================================
    // PARTIE 2 : CONNEXION AVEC UNE PASSKEY
    // ================================================

    /**
     * Génère les options pour la connexion par passkey
     */
    public function getLoginOptions(): array
    {
        $challenge = random_bytes(32);

        $options = PublicKeyCredentialRequestOptions::create(
            challenge: $challenge,
            rpId: $this->rpId,
        );

        // Sauvegarde en session
        $session = $this->requestStack->getSession();
        $session->set('webauthn_login', serialize($options));

        return $this->toArray($options);
    }

    /**
     * Valide la connexion et retourne l'utilisateur authentifié
     */
    public function verifyLogin(array $credentialData): User
    {
        $session = $this->requestStack->getSession();
        $options = unserialize($session->get('webauthn_login'));

        $factory   = new CeremonyStepManagerFactory();
        $validator = AuthenticatorAssertionResponseValidator::create(
            $factory->requestAuthentication()
        );

        // Décode la réponse du navigateur
        $publicKeyCredential = PublicKeyCredential::createFromArray($credentialData);

        if (!$publicKeyCredential->response instanceof AuthenticatorAssertionResponse) {
            throw new \Exception('Réponse invalide — type attendu : assertion');
        }

        // Trouve la credential en base via son ID
        $credentialId = base64_decode(
            strtr($credentialData['id'], '-_', '+/')
        );

        $credentialEntity = $this->credRepo->findByCredentialId($credentialId);

        if (!$credentialEntity) {
            throw new \Exception('Passkey inconnue — non enregistrée');
        }

        $source = $credentialEntity->getCredentialSource();

        // Vérifie et valide
        $updatedSource = $validator->check(
            $source,
            $publicKeyCredential->response,
            $options,
            $this->rpId,
            null,
            ['https://' . $this->rpId, 'http://' . $this->rpId]
        );

        // Met à jour la date de dernière utilisation
        $credentialEntity->touch();
        $credentialEntity->setCredentialSource($updatedSource);
        $this->em->flush();

        $session->remove('webauthn_login');

        return $credentialEntity->getUser();
    }

    /**
     * Retourne la liste des credentials déjà enregistrés
     * Pour éviter les doublons lors de l'enregistrement
     */
    private function getExcludedCredentials(User $user): array
    {
        return [];
    }
}