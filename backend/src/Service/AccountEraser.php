<?php

namespace App\Service;

use App\Entity\AccessKey;
use App\Entity\AiKey;
use App\Entity\ApiToken;
use App\Entity\GameSession;
use App\Entity\Quiz;
use App\Entity\User;
use App\Repository\LiveGameRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Droit à l'effacement (RGPD, art. 17). Tout ce qui se rattache à la personne
 * disparaît : compte, jetons, historique, participations aux parties en direct.
 * Les quiz rédigés restent disponibles pour les autres, mais anonymisés.
 */
class AccountEraser
{
    public const ANONYMOUS = 'compte supprimé';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LiveGameRepository $liveGames,
    ) {
    }

    public function erase(User $user): void
    {
        $this->em->wrapInTransaction(function () use ($user): void {
            $this->em->createQueryBuilder()->delete(GameSession::class, 's')
                ->andWhere('s.user = :user')->setParameter('user', $user)
                ->getQuery()->execute();

            $this->em->createQueryBuilder()->delete(ApiToken::class, 't')
                ->andWhere('t.user = :user')->setParameter('user', $user)
                ->getQuery()->execute();

            $this->em->createQueryBuilder()->update(Quiz::class, 'q')
                ->set('q.owner', ':none')->set('q.author', ':anonymous')
                ->andWhere('q.owner = :user')
                ->setParameter('none', null)->setParameter('anonymous', self::ANONYMOUS)->setParameter('user', $user)
                ->getQuery()->execute();

            $this->em->createQueryBuilder()->update(AccessKey::class, 'k')
                ->set('k.createdBy', ':anonymous')
                ->andWhere('k.createdBy = :name')
                ->setParameter('anonymous', self::ANONYMOUS)->setParameter('name', $user->getUsername())
                ->getQuery()->execute();

            $this->em->createQueryBuilder()->update(AccessKey::class, 'k')
                ->set('k.assignedTo', ':none')->set('k.assignedToName', ':anonymous')
                ->andWhere('k.assignedTo = :user')
                ->setParameter('none', null)->setParameter('anonymous', self::ANONYMOUS)->setParameter('user', $user)
                ->getQuery()->execute();

            // Le nombre de générations restantes n'est pas rendu : la clé IA disparaît avec le compte qui l'a saisie.
            $this->em->createQueryBuilder()->update(AiKey::class, 'k')
                ->set('k.redeemedBy', ':none')->set('k.redeemedByName', ':anonymous')
                ->andWhere('k.redeemedBy = :user')
                ->setParameter('none', null)->setParameter('anonymous', self::ANONYMOUS)->setParameter('user', $user)
                ->getQuery()->execute();

            $this->em->createQueryBuilder()->update(AiKey::class, 'k')
                ->set('k.createdBy', ':anonymous')
                ->andWhere('k.createdBy = :name')
                ->setParameter('anonymous', self::ANONYMOUS)->setParameter('name', $user->getUsername())
                ->getQuery()->execute();

            $this->liveGames->removeAll($this->liveGames->findBy(['host' => $user]));

            foreach ($this->liveGames->findParticipations($user) as $participation) {
                $participation->getGame()->removePlayer($participation);
                $this->em->remove($participation);
            }

            $this->em->remove($user);
            $this->em->flush();
        });
    }
}
