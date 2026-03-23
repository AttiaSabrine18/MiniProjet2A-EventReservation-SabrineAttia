<?php

namespace App\Repository;

use App\Entity\WebauthnCredential;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WebauthnCredential>
 */
class WebauthnCredentialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebauthnCredential::class);
    }

//    /**
//     * @return WebauthnCredential[] Returns an array of WebauthnCredential objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('w')
//            ->andWhere('w.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('w.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?WebauthnCredential
//    {
//        return $this->createQueryBuilder('w')
//            ->andWhere('w.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

public function saveCredential(User $user, PublicKeyCredentialSource $credentialSource): void
{
    // On "hydrate" notre entité avec les données du credential source
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
}
