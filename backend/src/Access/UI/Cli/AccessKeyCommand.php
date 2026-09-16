<?php

namespace App\Access\UI\Cli;

use App\Access\Application\IssueAccessKey;
use App\Access\Domain\Model\AccessKey;
use App\Access\Domain\Repository\AccessKeyRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Commande console pour créer ou lister les clés d'accès. */
#[AsCommand(name: 'app:access-key', description: "Crée ou liste les clés d'accès (prof / admin)")]
class AccessKeyCommand
{
    public function __construct(
        private readonly AccessKeyRepository $keys,
        private readonly IssueAccessKey $issueAccessKey,
        private readonly ClockInterface $clock,
    ) {
    }

    /** Crée une clé pour le rôle demandé, ou liste les clés existantes sans argument. */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Rôle accordé par la clé : prof ou admin')]
        ?string $role = null,
        #[Option(description: 'Étiquette libre, pour savoir à qui la clé est remise')]
        ?string $label = null,
        #[Option(description: "Durée de validité en jours (1 à 365) ; sans l'option, la clé n'expire pas")]
        ?int $expires = null,
        #[Option(description: 'Liste les clés existantes')]
        bool $list = false,
    ): int {
        if ($list || null === $role) {
            return $this->list($io);
        }

        if (!in_array($role, AccessKey::ROLES, true)) {
            $io->error(sprintf('Rôle inconnu « %s ». Valeurs acceptées : %s.', $role, implode(', ', AccessKey::ROLES)));

            return Command::INVALID;
        }

        if (null !== $expires && ($expires < 1 || $expires > 365)) {
            $io->error('L’expiration doit être comprise entre 1 et 365 jours.');

            return Command::INVALID;
        }

        $key = $this->issueAccessKey->issue($role, $label, $expires, null, 'console');

        $io->success(sprintf('Clé %s créée.', $role));
        $io->definitionList(
            ['Clé' => $key->getValue()],
            ['Rôle' => $role],
            ['Étiquette' => $label ?? '—'],
            ['Expire le' => $key->getExpiresAt()?->format('d/m/Y H:i') ?? 'jamais'],
        );

        return Command::SUCCESS;
    }

    /** Affiche toutes les clés dans un tableau avec leur état. */
    private function list(SymfonyStyle $io): int
    {
        $keys = $this->keys->findAllOrdered();

        if (!$keys) {
            $io->warning('Aucune clé enregistrée. Crée-en une : php bin/console app:access-key prof');

            return Command::SUCCESS;
        }

        $now = $this->clock->now();
        $states = [
            AccessKey::STATUS_ACTIVE => 'active',
            AccessKey::STATUS_ASSIGNED => 'attribuée',
            AccessKey::STATUS_EXPIRED => 'périmée',
            AccessKey::STATUS_REVOKED => 'révoquée',
        ];

        $io->table(
            ['Clé', 'Rôle', 'Étiquette', 'État', 'Expire le', 'Utilisations'],
            array_map(static fn (AccessKey $key) => [
                $key->getValue(),
                $key->getRole(),
                $key->getLabel() ?? '—',
                $states[$key->status($now)],
                $key->getExpiresAt()?->format('d/m/Y') ?? '—',
                $key->getUsageCount(),
            ], $keys),
        );

        return Command::SUCCESS;
    }
}
