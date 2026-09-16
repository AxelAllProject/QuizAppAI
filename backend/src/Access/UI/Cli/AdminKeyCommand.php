<?php

namespace App\Access\UI\Cli;

use App\Shared\Application\AdminKeyGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/** Commande console pour générer ou afficher la clé de secours ADMIN_CODE. */
#[AsCommand(name: 'app:admin-key', description: "Génère ou affiche la clé d'accès administrateur")]
class AdminKeyCommand
{
    public function __construct(
        private readonly AdminKeyGenerator $generator,
        private readonly Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%env(ADMIN_CODE)%')]
        private readonly string $currentKey,
    ) {
    }

    /** Génère une nouvelle clé de secours dans .env.local, ou affiche l'actuelle avec --show. */
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Affiche la clé actuelle sans en générer une nouvelle')]
        bool $show = false,
    ): int {
        if ($show) {
            $io->section('Clé administrateur actuelle');
            $io->text($this->currentKey);

            return Command::SUCCESS;
        }

        $key = $this->generator->generate();
        $file = $this->projectDir.'/.env.local';
        $this->write($file, $key);

        $io->success('Nouvelle clé administrateur générée.');
        $io->definitionList(
            ['Clé' => $key],
            ['Enregistrée dans' => '.env.local (non versionné)'],
        );
        $io->warning("L'ancienne clé ne fonctionne plus. Redémarre le serveur pour qu'elle soit prise en compte.");

        return Command::SUCCESS;
    }

    /** Réécrit uniquement la ligne ADMIN_CODE, en préservant le reste du fichier. */
    private function write(string $file, string $key): void
    {
        $line = sprintf('ADMIN_CODE=%s', $key);
        $content = $this->filesystem->exists($file) ? file_get_contents($file) : '';

        if (preg_match('/^ADMIN_CODE=.*$/m', $content)) {
            $content = preg_replace('/^ADMIN_CODE=.*$/m', $line, $content);
        } else {
            $content = rtrim($content, "\n");
            $content = ('' === $content ? '' : $content."\n").$line."\n";
        }

        $this->filesystem->dumpFile($file, $content);
    }
}
