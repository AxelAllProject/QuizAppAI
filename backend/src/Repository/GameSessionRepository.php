<?php

namespace App\Repository;

use App\Entity\GameSession;
use App\Entity\Quiz;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<GameSession> */
class GameSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameSession::class);
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
     * Toutes les parties d'un quiz, du meilleur score au moins bon.
     * À score égal, la partie la plus rapide passe devant.
     *
     * @return GameSession[]
     */
    public function findByQuiz(int $quizId): array
    {
        $sessions = $this->createQueryBuilder('s')
            ->andWhere('s.quiz = :quiz')->setParameter('quiz', $quizId)
            ->getQuery()
            ->getResult();

        usort($sessions, static function (GameSession $a, GameSession $b): int {
            $ratio = static fn (GameSession $s): float => $s->getTotal() > 0 ? $s->getScore() / $s->getTotal() : 0.0;

            return [$ratio($b), -($a->getDurationSeconds() ?? PHP_INT_MAX)]
                <=> [$ratio($a), -($b->getDurationSeconds() ?? PHP_INT_MAX)];
        });

        return $sessions;
    }

    /** Classement : meilleur pourcentage par joueur. */
    public function leaderboard(int $limit = 10): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('s.player AS player, COUNT(s.id) AS games, SUM(s.score) AS score, SUM(s.total) AS total')
            ->groupBy('s.player')
            ->getQuery()
            ->getResult();

        $rows = array_map(static function (array $row): array {
            $total = (int) $row['total'];
            $score = (int) $row['score'];

            return [
                'player' => $row['player'],
                'games' => (int) $row['games'],
                'score' => $score,
                'total' => $total,
                'accuracy' => $total > 0 ? round($score / $total * 100) : 0,
            ];
        }, $rows);

        usort($rows, static fn (array $a, array $b) => [$b['accuracy'], $b['games']] <=> [$a['accuracy'], $a['games']]);

        return array_slice($rows, 0, $limit);
    }
}
