<?php

namespace App\Analytics\Infrastructure\Persistence;

use App\Analytics\Domain\Model\AnalyticsEvent;
use App\Analytics\Domain\Repository\AnalyticsEventRepository;
use App\Identity\Domain\Model\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Agrégations du tableau de bord, toutes faites en base.
 *
 * Le regroupement par jour passe par SUBSTRING sur l'horodatage : la date est stockée
 * au format « AAAA-MM-JJ HH:MM:SS », dont les dix premiers caractères sont le jour.
 * C'est du DQL standard, là où DATE() ou DATE_FORMAT() dépendraient du moteur.
 *
 * @extends ServiceEntityRepository<AnalyticsEvent>
 */
#[AsAlias(AnalyticsEventRepository::class)]
class DoctrineAnalyticsEventRepository extends ServiceEntityRepository implements AnalyticsEventRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalyticsEvent::class);
    }

    /** Prépare l'enregistrement d'un événement. */
    public function add(AnalyticsEvent $event): void
    {
        $this->getEntityManager()->persist($event);
    }

    /** Nombre de comptes distincts ayant agi depuis cette date. */
    public function activeAccountsSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(DISTINCT e.user)')
            ->andWhere('e.occurredAt >= :since')
            ->andWhere('e.user IS NOT NULL')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array<string, int> */
    public function dailyCounts(string $name, \DateTimeImmutable $since): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('SUBSTRING(e.occurredAt, 1, 10) AS day, COUNT(e.id) AS total')
            ->andWhere('e.name = :name')
            ->andWhere('e.occurredAt >= :since')
            ->setParameter('name', $name)
            ->setParameter('since', $since)
            ->groupBy('day')
            ->orderBy('day', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'total', 'day');
    }

    /** @return array<string, int> */
    public function dailyActiveAccounts(\DateTimeImmutable $since): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('SUBSTRING(e.occurredAt, 1, 10) AS day, COUNT(DISTINCT e.user) AS total')
            ->andWhere('e.occurredAt >= :since')
            ->andWhere('e.user IS NOT NULL')
            ->setParameter('since', $since)
            ->groupBy('day')
            ->orderBy('day', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'total', 'day');
    }

    /** @return array<string, int> */
    public function countsByRole(string $name, \DateTimeImmutable $since): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('e.role AS role, COUNT(e.id) AS total')
            ->andWhere('e.name = :name')
            ->andWhere('e.occurredAt >= :since')
            ->setParameter('name', $name)
            ->setParameter('since', $since)
            ->groupBy('e.role')
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'total', 'role');
    }

    /** @return list<array{path: string, views: int, visitors: int, averageMs: int}> */
    public function topPaths(\DateTimeImmutable $since, int $limit = 10): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('e.path AS path, COUNT(e.id) AS views, COUNT(DISTINCT e.user) AS visitors, AVG(e.durationMs) AS averageMs')
            ->andWhere('e.name = :name')
            ->andWhere('e.occurredAt >= :since')
            ->andWhere('e.path IS NOT NULL')
            ->setParameter('name', AnalyticsEvent::PAGE_VIEW)
            ->setParameter('since', $since)
            ->groupBy('e.path')
            ->orderBy('views', 'DESC')
            ->addOrderBy('path', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row) => [
            'path' => (string) $row['path'],
            'views' => (int) $row['views'],
            'visitors' => (int) $row['visitors'],
            'averageMs' => (int) round((float) $row['averageMs']),
        ], $rows);
    }

    /** @return array<string, int> */
    public function topSubjects(string $name, \DateTimeImmutable $since, int $limit = 10): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('e.subject AS subject, COUNT(e.id) AS total')
            ->andWhere('e.name = :name')
            ->andWhere('e.occurredAt >= :since')
            ->andWhere('e.subject IS NOT NULL')
            ->setParameter('name', $name)
            ->setParameter('since', $since)
            ->groupBy('e.subject')
            ->orderBy('total', 'DESC')
            ->addOrderBy('subject', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'total', 'subject');
    }

    /** Nombre total d'événements portant ce nom, éventuellement filtré sur leur issue. */
    public function countEvents(string $name, \DateTimeImmutable $since, ?string $outcome = null): int
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.name = :name')
            ->andWhere('e.occurredAt >= :since')
            ->setParameter('name', $name)
            ->setParameter('since', $since);

        if (null !== $outcome) {
            $qb->andWhere('e.outcome = :outcome')->setParameter('outcome', $outcome);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** @return list<array{event: string, count: int, firstAt: string, lastAt: string}> */
    public function summaryFor(User $user): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('e.name AS event, COUNT(e.id) AS count, MIN(e.occurredAt) AS firstAt, MAX(e.occurredAt) AS lastAt')
            ->andWhere('e.user = :user')
            ->setParameter('user', $user)
            ->groupBy('e.name')
            ->orderBy('event', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row) => [
            'event' => (string) $row['event'],
            'count' => (int) $row['count'],
            'firstAt' => (string) $row['firstAt'],
            'lastAt' => (string) $row['lastAt'],
        ], $rows);
    }

    /** Supprime les événements arrivés en fin de conservation, en une requête. */
    public function deleteBefore(\DateTimeImmutable $limit): int
    {
        return $this->createQueryBuilder('e')
            ->delete()
            ->andWhere('e.occurredAt < :limit')
            ->setParameter('limit', $limit)
            ->getQuery()
            ->execute();
    }

    /** Coupe le lien vers un compte supprimé, en une requête : les compteurs restent justes. */
    public function detachUser(User $user): void
    {
        $this->createQueryBuilder('e')
            ->update()
            ->set('e.user', 'NULL')
            ->andWhere('e.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
