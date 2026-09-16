<?php

namespace App\Identity\Application;

use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\ApiTokenRepository;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Application\UnitOfWork;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Droit à l'effacement (RGPD, art. 17). Tout ce qui se rattache à la personne
 * disparaît : compte, jetons, historique, participations aux parties en direct.
 * Les quiz rédigés restent disponibles pour les autres, mais anonymisés.
 *
 * Identity ne connaît pas les autres contextes : chacun fournit son PersonalDataEraser.
 */
class AccountEraser
{
    /** @param iterable<PersonalDataEraser> $erasers */
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly UserRepository $users,
        private readonly ApiTokenRepository $tokens,
        #[AutowireIterator(PersonalDataEraser::class)]
        private readonly iterable $erasers,
    ) {
    }

    public function erase(User $user): void
    {
        $this->unitOfWork->transactional(function () use ($user): void {
            $this->tokens->deleteByUser($user);

            foreach ($this->erasers as $eraser) {
                $eraser->erase($user);
            }

            $this->users->remove($user);
            $this->unitOfWork->flush();
        });
    }
}
