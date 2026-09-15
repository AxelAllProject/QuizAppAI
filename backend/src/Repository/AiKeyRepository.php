<?php

namespace App\Repository;

use App\Entity\AiKey;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Clock\ClockInterface;

/** @extends ServiceEntityRepository<AiKey> */
class AiKeyRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($registry, AiKey::class);
    }

    public function findByValue(string $value): ?AiKey
    {
        return $this->findOneBy(['value' => $value]);
    }

    /** La clé la plus récemment liée à ce compte qui a encore des générations disponibles. */
    public function findActiveFor(User $user): ?AiKey
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.redeemedBy = :user')
            ->andWhere('k.revokedAt IS NULL')
            ->andWhere('k.remainingGenerations > 0')
            ->andWhere('k.expiresAt IS NULL OR k.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('now', $this->clock->now())
            ->orderBy('k.redeemedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return AiKey[] */
    public function findAllOrdered(): array
    {
        return $this->search();
    }

    /**
     * Listing du back-office : recherche libre (code, étiquette, détenteur) et filtre d'état.
     * « active » veut dire réellement utilisable : ni révoquée, ni périmée, ni épuisée.
     *
     * @return AiKey[]
     */
    public function search(?string $term = null, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('k')
            ->orderBy('k.createdAt', 'DESC')
            // Départage les clés créées à la même seconde : sans ça l'ordre du listing varie.
            ->addOrderBy('k.id', 'DESC');

        if (null !== $term && '' !== trim($term)) {
            $qb->andWhere('LOWER(k.value) LIKE :term OR LOWER(k.label) LIKE :term OR LOWER(k.redeemedByName) LIKE :term')
                ->setParameter('term', '%'.mb_strtolower(trim($term)).'%');
        }

        // Un paramètre déclaré mais absent du DQL fait échouer la requête : on ne lie
        // `now` que dans les branches qui s'en servent réellement.
        match ($status) {
            null => null,
            'revoked' => $qb->andWhere('k.revokedAt IS NOT NULL'),
            'expired' => $qb
                ->andWhere('k.revokedAt IS NULL AND k.expiresAt IS NOT NULL AND k.expiresAt <= :now')
                ->setParameter('now', $this->clock->now()),
            'exhausted' => $qb->andWhere('k.revokedAt IS NULL AND k.remainingGenerations <= 0'),
            'unclaimed' => $qb->andWhere('k.revokedAt IS NULL AND k.redeemedBy IS NULL'),
            default => $qb
                ->andWhere('k.revokedAt IS NULL')
                ->andWhere('k.remainingGenerations > 0')
                ->andWhere('k.expiresAt IS NULL OR k.expiresAt > :now')
                ->setParameter('now', $this->clock->now()),
        };

        return $qb->getQuery()->getResult();
    }
}
