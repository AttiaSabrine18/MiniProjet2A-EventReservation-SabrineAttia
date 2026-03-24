<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use App\Entity\EmailVerification;
use App\Entity\WebauthnCredential;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isVerified = false;

    #[ORM\OneToMany(
        mappedBy: 'user',
        targetEntity: WebauthnCredential::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $webauthnCredentials;

    #[ORM\OneToMany(
        mappedBy: 'user',
        targetEntity: EmailVerification::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $emailVerifications;

    public function __construct()
    {
        $this->webauthnCredentials = new ArrayCollection();
        $this->emailVerifications = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void {}

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
        return $this;
    }

    public function getEmailVerifications(): Collection
    {
        return $this->emailVerifications;
    }

    public function addEmailVerification(EmailVerification $verification): static
    {
        if (!$this->emailVerifications->contains($verification)) {
            $this->emailVerifications->add($verification);
            $verification->setUser($this);
        }
        return $this;
    }

    public function removeEmailVerification(EmailVerification $verification): static
    {
        if ($this->emailVerifications->removeElement($verification)) {
            if ($verification->getUser() === $this) {
                $verification->setUser(null);
            }
        }
        return $this;
    }

    public function getValidVerificationToken(): ?EmailVerification
    {
        foreach ($this->emailVerifications as $verification) {
            if ($verification->isValid()) {
                return $verification;
            }
        }
        return null;
    }

    public function getWebauthnCredentials(): Collection
    {
        return $this->webauthnCredentials;
    }

    public function addWebauthnCredential(WebauthnCredential $credential): static
    {
        if (!$this->webauthnCredentials->contains($credential)) {
            $this->webauthnCredentials->add($credential);
            $credential->setUser($this);
        }
        return $this;
    }

    public function removeWebauthnCredential(WebauthnCredential $credential): static
    {
        if ($this->webauthnCredentials->removeElement($credential)) {
            if ($credential->getUser() === $this) {
                $credential->setUser(null);
            }
        }
        return $this;
    }
}