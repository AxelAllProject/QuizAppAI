<?php

namespace App\Live\Infrastructure\Persistence;

use App\Identity\Domain\Model\User;
use App\Live\Domain\Model\LiveGame;
use App\Live\Domain\Model\LivePlayer;
use App\Live\Domain\Repository\LiveGameRepository;
use App\Quiz\Domain\Model\Quiz;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/** @extends ServiceEntityRepository<LiveGame> */
#[AsAlias(LiveGameRepository::class)]
class DoctrineLiveGameRepository extends ServiceEntityRepository implements LiveGameRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LiveGame::class);
    }

    /**
     * Un code PIN finit par resservir : on prend la partie la plus récente.
     *
     * Chaque écran interroge l'état toutes les secondes : tout ce qu'il affiche est chargé
     * d'un coup (animateur, quiz et questions, puis joueurs et réponses), en un nombre de
     * requêtes qui ne dépend pas du nombre de joueurs. Deux requêtes plutôt qu'une : joindre
     * questions et réponses ensemble multiplierait les lignes (questions × réponses).
     */
    public function findLatestByPin(string $pin): ?LiveGame
    {
        $id = $this->createQueryBuilder('g')
            ->select('MAX(g.id)')
            ->andWhere('g.pin = :pin')
            ->setParameter('pin', $pin)
            ->getQuery()
            ->getSingleScalarResult();

        if (null === $id) {
            return null;
        }

        $game = $this->createQueryBuilder('g')
            ->addSelect('host', 'quiz', 'question')
            ->join('g.host', 'host')
            ->join('g.quiz', 'quiz')
            ->leftJoin('quiz.questions', 'question')
            ->andWhere('g.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        $this->createQueryBuilder('g')
            ->addSelect('player', 'answer')
            ->leftJoin('g.players', 'player')
            ->leftJoin('player.answers', 'answer')
            ->andWhere('g.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getResult();

        return $game;
    }

    /** Indique si le code PIN est déjà pris par une partie non terminée. */
    public function isPinInUse(string $pin): bool
    {
        return (bool) $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.pin = :pin')
            ->andWhere('g.status != :finished')
            ->setParameter('pin', $pin)
            ->setParameter('finished', LiveGame::STATUS_FINISHED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return LiveGame[] */
    public function findCreatedBefore(\DateTimeImmutable $limit): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.createdAt < :limit')
            ->setParameter('limit', $limit)
            ->getQuery()
            ->getResult();
    }

    /** @return LiveGame[] */
    public function findByHost(User $host): array
    {
        return $this->findBy(['host' => $host]);
    }

    /** @return LivePlayer[] */
    public function findParticipations(User $user): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('p', 'g')
            ->from(LivePlayer::class, 'p')
            ->join('p.game', 'g')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.joinedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Supprime les parties avec leurs joueurs et leurs réponses. SQLite n'applique pas
     * les clés étrangères : on passe par les cascades de l'ORM plutôt que par le ON DELETE.
     *
     * @param iterable<LiveGame> $games
     */
    public function removeAll(iterable $games): void
    {
        foreach ($games as $game) {
            $this->getEntityManager()->remove($game);
        }
    }

    /** Supprime toutes les parties jouées sur un quiz. */
    public function removeForQuiz(Quiz $quiz): void
    {
        $this->removeAll($this->findBy(['quiz' => $quiz]));
    }

    /** Retire le joueur de sa partie et le supprime avec ses réponses. */
    public function removePlayer(LivePlayer $player): void
    {
        $player->getGame()->removePlayer($player);
        $this->getEntityManager()->remove($player);
    }

    /** Prépare l'enregistrement d'une nouvelle partie. */
    public function add(LiveGame $liveGame): void
    {
        $this->getEntityManager()->persist($liveGame);
    }
}
