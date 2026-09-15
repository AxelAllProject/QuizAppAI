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
