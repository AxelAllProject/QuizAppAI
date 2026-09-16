<?php

namespace App\Identity\Application;

use App\Identity\Domain\Model\User;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/** Droit d'accès et à la portabilité (RGPD, art. 15 et 20) : tout ce qu'on sait de la personne, en JSON. */
class AccountExporter
{
    /** @param iterable<PersonalDataExporter> $exporters */
    public function __construct(
        private readonly AccountNormalizer $accounts,
        private readonly ClockInterface $clock,
        #[AutowireIterator(PersonalDataExporter::class)]
        private readonly iterable $exporters,
    ) {
    }

    public function export(User $user): array
    {
        $export = [
            'exportedAt' => $this->clock->now()->format(\DateTimeInterface::ATOM),
            'account' => $this->accounts->me($user) + [
                'lastSeenAt' => $user->getLastSeenAt()->format(\DateTimeInterface::ATOM),
            ],
        ];

        foreach ($this->exporters as $exporter) {
            $export += $exporter->export($user);
        }

        return $export;
    }
}
