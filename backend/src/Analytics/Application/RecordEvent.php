<?php

namespace App\Analytics\Application;

use App\Analytics\Domain\Model\AnalyticsEvent;
use App\Analytics\Domain\Repository\AnalyticsEventRepository;
use App\Identity\Domain\Model\User;
use App\Shared\Application\UnitOfWork;

/**
 * Point d'entrée unique pour mesurer un fait. Toute écriture passe ici, ce qui laisse
 * un seul endroit à relire pour vérifier ce qui est enregistré — et ce qui ne l'est pas.
 */
class RecordEvent
{
    public function __construct(
        private readonly AnalyticsEventRepository $events,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    /** Enregistre un fait et l'écrit tout de suite. */
    public function record(string $name, ?User $user, ?string $path = null, ?string $subject = null, ?string $outcome = null, ?int $durationMs = null): void
    {
        $this->events->add(
            (new AnalyticsEvent($name))
                ->by($user)
                ->setPath($path)
                ->setSubject($subject)
                ->setOutcome($outcome)
                ->setDurationMs($durationMs)
        );

        $this->unitOfWork->flush();
    }
}
