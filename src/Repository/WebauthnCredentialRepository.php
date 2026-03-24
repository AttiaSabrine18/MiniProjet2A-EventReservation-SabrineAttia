<?php
// src/Repository/WebauthnCredentialRepository.php

namespace App\Repository;

use App\Entity\User;
use App\Entity\WebauthnCredential;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Webauthn\PublicKeyCredentialSource;

/**
 * @extends ServiceEntityRepository<WebauthnCredential>
 */
class WebauthnCredentialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebauthnCredential::class);
    }

    /**
     * Sauvegarde une nouvelle credential en base
     */
    public function saveCredential(User $user, PublicKeyCredentialSource $credentialSource): void
    {
        // Créer l'entité WebauthnCredential
        $webauthnCredential = new WebauthnCredential(
            $credentialSource->getPublicKeyCredentialId(),
            $credentialSource->getType(),
            $credentialSource->getTransports(),
            $credentialSource->getAttestationType(),
            $credentialSource->getTrustPath(),
            $credentialSource->getAaguid(),
            $credentialSource->getCredentialPublicKey(),
            $credentialSource->getUserHandle(),
            $credentialSource->getCounter()
        );
        
        $webauthnCredential->setUser($user);
        
        $this->getEntityManager()->persist($webauthnCredential);
        $this->getEntityManager()->flush();
    }

    /**
     * Trouve une credential par son ID (binaire)
     */
    public function findByCredentialId(string $credentialId): ?WebauthnCredential
    {
        return $this->createQueryBuilder('c')
            ->where('c.publicKeyCredentialId = :id')
            ->setParameter('id', $credentialId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve toutes les credentials d'un utilisateur
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }
}