<?php

namespace App\Game\Infrastructure\Persistence;

use App\Game\Domain\Model\GameSession;
use App\Game\Domain\Repository\GameSessionRepository;
use App\Identity\Domain\Model\User;
use App\Quiz\Domain\Model\Quiz;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/** @extends ServiceEntityRepository<GameSession> */
#[AsAlias(GameSessionRepository::class)]
class DoctrineGameSessionRepository extends ServiceEntityRepository implements GameSessionRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameSession::class);
    }

    /** Récupère une partie par son identifiant. */
    public function ofId(int $id): ?GameSession
    {
        return $this->find($id);
    }

    /**
     * Parties d'un compte, ou de tout le monde si $user est null.
     *
     * @return GameSession[]
     */
    public function findHistory(?User $user, ?int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('s')
            ->orderBy('s.playedAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults($limit);

        if (null !== $user) {
            $qb->andWhere('s.user = :user')->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /** Les parties d'un quiz supprimé restent dans l'historique, grâce au titre recopié. */
    public function detachQuiz(Quiz $quiz): void
    {
        $this->createQueryBuilder('s')
            ->update()
            ->set('s.quiz', ':none')
            ->andWhere('s.quiz = :quiz')
            ->setParameter('none', null)
            ->setParameter('quiz', $quiz)
            ->getQuery()
            ->execute();
    }

    /**
     * Classement d'un quiz : la première partie de chaque joueur, du meilleur score au
     * moins bon ; à score égal, la plus rapide passe devant (sans durée : en dernier).
     *
     * Seule la première partie compte : la correction affichée à la fin donne les bonnes
     * réponses, rejouer juste derrière suffirait sinon pour finir premier. Tri et limite
     * sont faits en base, sans charger toutes les parties du quiz.
     *
     * @return GameSession[]
     */
    public function findByQuiz(int $quizId, int $limit = 100): array
    {
        $firstAttempts = $this->getEntityManager()->createQueryBuilder()
            ->select('MIN(f.id)')
            ->from(GameSession::class, 'f')
            ->andWhere('f.quiz = :quiz')
            ->groupBy('f.user');

        return $this->createQueryBuilder('s')
            ->addSelect('CASE WHEN s.total > 0 THEN s.score * 1.0 / s.total ELSE 0 END AS HIDDEN accuracy')
            ->addSelect('CASE WHEN s.durationSeconds IS NULL THEN 1 ELSE 0 END AS HIDDEN withoutDuration')
            ->andWhere(sprintf('s.id IN (%s)', $firstAttempts->getDQL()))
            ->setParameter('quiz', $quizId)
            ->orderBy('accuracy', 'DESC')
            ->addOrderBy('withoutDuration', 'ASC')
            ->addOrderBy('s.durationSeconds', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Classement général : taux de bonnes réponses de chaque joueur, puis nombre de parties.
     * Tri et limite faits en base : seuls les $limit premiers joueurs sont lus, quel que soit
     * le nombre de joueurs. Le tri se fait sur le taux exact (67,4 % passe devant 66,6 %).
     */
    public function leaderboard(int $limit = 10): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('s.player AS player, COUNT(s.id) AS games, SUM(s.score) AS score, SUM(s.total) AS total')
            ->addSelect('SUM(s.score) * 1.0 / SUM(s.total) AS HIDDEN ratio')
            ->groupBy('s.player')
            ->having('SUM(s.total) > 0')
            ->orderBy('ratio', 'DESC')
            ->addOrderBy('games', 'DESC')
            ->addOrderBy('s.player', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => [
            'player' => $row['player'],
            'games' => (int) $row['games'],
            'score' => (int) $row['score'],
            'total' => (int) $row['total'],
            'accuracy' => (int) round($row['score'] / $row['total'] * 100),
        ], $rows);
    }

    /** Compte les parties, bonnes réponses et questions de chaque compte, en une requête GROUP BY. */
    public function statsByUser(array $users): array
    {
        if ([] === $users) {
            return [];
        }

        $rows = $this->createQueryBuilder('s')
            ->select('IDENTITY(s.user) AS userId, COUNT(s.id) AS games, SUM(s.score) AS score, SUM(s.total) AS total')
            ->andWhere('s.user IN (:users)')
            ->setParameter('users', $users)
            ->groupBy('s.user')
            ->getQuery()
            ->getArrayResult();

        $stats = [];

        foreach ($rows as $row) {
            $stats[(int) $row['userId']] = ['games' => (int) $row['games'], 'score' => (int) $row['score'], 'total' => (int) $row['total']];
        }

        return $stats;
    }

    /** Calcule en base les totaux du tableau de bord (parties, joueurs, score, questions). */
    public function globalStats(): array
    {
        $row = $this->createQueryBuilder('s')
            ->select('COUNT(s.id) AS sessionCount, COUNT(DISTINCT s.player) AS playerCount, COALESCE(SUM(s.score), 0) AS score, COALESCE(SUM(s.total), 0) AS total')
            ->getQuery()
            ->getSingleResult();

        return array_map(intval(...), $row);
    }

    /** Nombre de parties du joueur et moyenne de ses taux de réussite, calculés en base. */
    public function summaryFor(User $user): array
    {
        $row = $this->createQueryBuilder('s')
            ->select('COUNT(s.id) AS sessionCount')
            ->addSelect('AVG(CASE WHEN s.total > 0 THEN s.score * 1.0 / s.total ELSE 0 END) AS ratio')
            ->andWhere('s.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleResult();

        return [
            'sessionCount' => (int) $row['sessionCount'],
            'averageAccuracy' => null === $row['ratio'] ? null : (int) round($row['ratio'] * 100),
        ];
    }

    /** Supprime toutes les parties d'un compte, en une requête. */
    public function deleteByUser(User $user): void
    {
        $this->createQueryBuilder('s')
            ->delete()
            ->andWhere('s.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /** Prépare l'enregistrement d'une nouvelle partie. */
    public function add(GameSession $gameSession): void
    {
        $this->getEntityManager()->persist($gameSession);
    }
}
