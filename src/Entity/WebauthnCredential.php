<?php
// src/Entity/WebauthnCredential.php

namespace App\Entity;

use App\Repository\WebauthnCredentialRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Webauthn\PublicKeyCredentialSource;

#[ORM\Entity(repositoryClass: WebauthnCredentialRepository::class)]
#[ORM\Table(name: 'webauthn_credential')]
class WebauthnCredential extends PublicKeyCredentialSource
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'webauthnCredentials')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    public function __construct(string $publicKeyCredentialId, string $type, array $transports, string $attestationType, \Webauthn\TrustPath\TrustPathInterface $trustPath, \Webauthn\U2FPublicKey $aaguid, string $credentialPublicKey, string $userHandle, int $counter)
    {
        parent::__construct($publicKeyCredentialId, $type, $transports, $attestationType, $trustPath, $aaguid, $credentialPublicKey, $userHandle, $counter);
        $this->id = Uuid::v4();
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function touch(): void
    {
        $this->lastUsedAt = new \DateTimeImmutable();
    }
}