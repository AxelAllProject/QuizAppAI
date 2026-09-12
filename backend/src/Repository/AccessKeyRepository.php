<?php

namespace App\Repository;

use App\Entity\AccessKey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AccessKey> */
class AccessKeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccessKey::class);
    }

    /** Une clé révoquée ne donne plus aucun droit. */
    public function findActive(string $value): ?AccessKey
    {
        return $this->findOneBy(['value' => $value, 'revokedAt' => null]);
    }

    /** @return AccessKey[] */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('k')
            ->orderBy('k.revokedAt', 'ASC')
            ->addOrderBy('k.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
