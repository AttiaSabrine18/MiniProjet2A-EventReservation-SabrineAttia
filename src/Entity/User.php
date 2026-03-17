<?php
// src/Entity/User.php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    // ====== PROPRIÉTÉS ======

    /**
     * Identifiant unique de type UUID (plus sécurisé qu'un simple entier)
     * Doctrine génère automatiquement un UUID pour chaque nouvel utilisateur
     */
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    /**
     * Email de l'utilisateur — sert aussi d'identifiant de connexion
     */
    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    /**
     * Les rôles de l'utilisateur (ex: ROLE_USER, ROLE_ADMIN)
     * Stocké en JSON dans la base de données
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * Mot de passe hashé
     * Même si on utilise les Passkeys, Symfony Security en a besoin
     */
    #[ORM\Column]
    private ?string $password = null;

    /**
     * La liste des Passkeys (clés WebAuthn) liées à cet utilisateur
     * Un utilisateur peut avoir plusieurs Passkeys (téléphone, PC, tablette...)
     */
    #[ORM\OneToMany(
        mappedBy: 'user',
        targetEntity: WebauthnCredential::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $webauthnCredentials;

    // ====== CONSTRUCTEUR ======

    public function __construct()
    {
        // On initialise la collection vide dès la création de l'objet
        $this->webauthnCredentials = new ArrayCollection();
    }

    // ====== GETTERS ET SETTERS STANDARD ======

    /**
     * Retourne l'identifiant UUID de l'utilisateur
     */
    public function getId(): ?Uuid
    {
        return $this->id;
    }

    /**
     * Retourne l'email
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Modifie l'email
     */
    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    /**
     * Symfony utilise cette méthode pour identifier l'utilisateur
     * On retourne l'email comme identifiant unique
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * Retourne les rôles de l'utilisateur
     * ROLE_USER est toujours ajouté automatiquement
     *
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // Chaque utilisateur a au minimum ROLE_USER
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    /**
     * Modifie les rôles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    /**
     * Retourne le mot de passe hashé
     *
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Modifie le mot de passe (doit être hashé avant d'appeler cette méthode)
     */
    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    /**
     * Efface les données sensibles temporaires
     * (non utilisé ici mais requis par UserInterface)
     *
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // Si tu stockes un mot de passe en clair temporairement,
        // tu le vides ici. Ex: $this->plainPassword = null;
    }

    // ====== MÉTHODES POUR GÉRER LES PASSKEYS ======

    /**
     * Retourne toutes les Passkeys de l'utilisateur
     */
    public function getWebauthnCredentials(): Collection
    {
        return $this->webauthnCredentials;
    }

    /**
     * Ajoute une nouvelle Passkey à l'utilisateur
     * Vérifie qu'elle n'existe pas déjà pour éviter les doublons
     */
    public function addWebauthnCredential(WebauthnCredential $credential): static
    {
        if (!$this->webauthnCredentials->contains($credential)) {
            $this->webauthnCredentials->add($credential);
            // On lie la Passkey à cet utilisateur (côté propriétaire)
            $credential->setUser($this);
        }
        return $this;
    }

    /**
     * Supprime une Passkey de l'utilisateur
     */
    public function removeWebauthnCredential(WebauthnCredential $credential): static
    {
        if ($this->webauthnCredentials->removeElement($credential)) {
            // On détache l'utilisateur de la Passkey si elle pointe encore vers lui
            if ($credential->getUser() === $this) {
                $credential->setUser(null);
            }
        }
        return $this;
    }
}