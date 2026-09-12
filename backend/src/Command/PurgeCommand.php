<?php

namespace App\Command;

use App\Repository\ApiTokenRepository;
use App\Repository\LiveGameRepository;
use App\Repository\UserRepository;
use App\Service\AccountEraser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Durées de conservation (RGPD, art. 5-1-e) annoncées dans la politique de confidentialité.
 * À lancer chaque jour, par exemple via cron : « php bin/console app:rgpd:purge ».
 */
#[AsCommand(name: 'app:rgpd:purge', description: 'Supprime les jetons expirés, les parties en direct de plus de 24 h et les comptes inactifs depuis 3 ans')]
class PurgeCommand
{
    public const LIVE_GAME_RETENTION = '-1 day';
    public const INACTIVE_ACCOUNT_RETENTION = '-3 years';

    public function __construct(
        private readonly ApiTokenRepository $tokens,
        private readonly LiveGameRepository $liveGames,
        private readonly UserRepository $users,
        private readonly AccountEraser $eraser,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Affiche ce qui serait supprimé sans rien supprimer')]
        bool $dryRun = false,
    ): int {
        $games = $this->liveGames->findCreatedBefore(new \DateTimeImmutable(self::LIVE_GAME_RETENTION));
        $inactive = $this->users->findInactiveSince(new \DateTimeImmutable(self::INACTIVE_ACCOUNT_RETENTION));

        if ($dryRun) {
            $io->note(sprintf('%d partie(s) en direct et %d compte(s) inactif(s) seraient supprimés.', count($games), count($inactive)));

            return Command::SUCCESS;
        }

        $expiredTokens = $this->tokens->deleteExpired();

        $this->liveGames->removeAll($games);
        $this->em->flush();

        foreach ($inactive as $user) {
            $this->eraser->erase($user);
        }

        $io->success(sprintf(
            '%d jeton(s) expiré(s), %d partie(s) en direct et %d compte(s) inactif(s) supprimés.',
            $expiredTokens,
            count($games),
            count($inactive),
        ));

        return Command::SUCCESS;
    }
}
