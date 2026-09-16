<?php

namespace App\Identity\UI\Cli;

use App\Identity\Application\PurgeExpiredData;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/** À lancer chaque jour, par exemple via cron : « php bin/console app:rgpd:purge ». */
#[AsCommand(name: 'app:rgpd:purge', description: 'Supprime les jetons expirés, les parties en direct de plus de 24 h et les comptes inactifs depuis 3 ans')]
class PurgeCommand
{
    public function __construct(
        private readonly PurgeExpiredData $purge,
        private readonly ClockInterface $clock,
    ) {
    }

    /** Supprime les données arrivées en fin de conservation, ou affiche ce qui le serait avec --dry-run. */
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Affiche ce qui serait supprimé sans rien supprimer')]
        bool $dryRun = false,
    ): int {
        if ($dryRun) {
            $preview = $this->purge->preview($this->clock->now());
            $io->note(sprintf('%d partie(s) en direct et %d compte(s) inactif(s) seraient supprimés.', $preview['liveGames'], $preview['inactiveAccounts']));

            return Command::SUCCESS;
        }

        $purged = $this->purge->purge($this->clock->now());
        $io->success(sprintf('%d jeton(s) expiré(s), %d partie(s) en direct et %d compte(s) inactif(s) supprimés.', $purged['tokens'], $purged['liveGames'], $purged['inactiveAccounts']));

        return Command::SUCCESS;
    }
}
