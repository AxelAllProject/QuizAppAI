<?php

namespace App\Ai\Infrastructure\Persistence;

use App\Ai\Domain\Model\AiKey;
use App\Ai\Domain\Repository\AiKeyRepository;
use App\Identity\Domain\Model\User;
use App\Shared\Infrastructure\Persistence\LikePattern;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/** @extends ServiceEntityRepository<AiKey> */
#[AsAlias(AiKeyRepository::class)]
class DoctrineAiKeyRepository extends ServiceEntityRepository implements AiKeyRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($registry, AiKey::class);
    }

    /** Récupère une clé IA par son identifiant. */
    public function ofId(int $id): ?AiKey
    {
        return $this->find($id);
    }

    /** Récupère une clé IA par son code. */
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
            $qb->andWhere(sprintf('LOWER(k.value) LIKE :term %1$s OR LOWER(k.label) LIKE :term %1$s OR LOWER(k.redeemedByName) LIKE :term %1$s', LikePattern::ESCAPE))
                ->setParameter('term', LikePattern::contains($term));
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

    /** Le nombre de générations restantes n'est pas rendu : la clé IA disparaît avec le compte qui l'a saisie. */
    public function anonymizeUser(User $user, string $anonymous): void
    {
        $this->createQueryBuilder('k')
            ->update()
            ->set('k.redeemedBy', ':none')
            ->set('k.redeemedByName', ':anonymous')
            ->andWhere('k.redeemedBy = :user')
            ->setParameter('none', null)
            ->setParameter('anonymous', $anonymous)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();

        $this->createQueryBuilder('k')
            ->update()
            ->set('k.createdBy', ':anonymous')
            ->andWhere('k.createdBy = :name')
            ->setParameter('anonymous', $anonymous)
            ->setParameter('name', $user->getUsername())
            ->getQuery()
            ->execute();
    }

    /** Prépare l'enregistrement d'une nouvelle clé IA. */
    public function add(AiKey $aiKey): void
    {
        $this->getEntityManager()->persist($aiKey);
    }
}
