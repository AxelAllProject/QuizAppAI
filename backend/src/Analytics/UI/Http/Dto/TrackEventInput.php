<?php

namespace App\Analytics\UI\Http\Dto;

use App\Analytics\Domain\Model\AnalyticsEvent;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Ce que le frontend a le droit de déclarer. La liste des noms est fermée : sans ça,
 * n'importe qui pourrait remplir la table d'événements inventés depuis la console.
 * Les actions qui comptent vraiment sont mesurées côté serveur (ApiActivityListener).
 */
class TrackEventInput
{
    /** Seuls faits que le navigateur est le mieux placé pour connaître. */
    public const ALLOWED = [AnalyticsEvent::PAGE_VIEW, AnalyticsEvent::QUIZ_STARTED];

    public function __construct(
        #[Assert\Choice(choices: self::ALLOWED, message: 'Événement inconnu.')]
        public readonly string $name = AnalyticsEvent::PAGE_VIEW,

        #[Assert\Length(max: 200)]
        public readonly ?string $path = null,

        #[Assert\Length(max: 60)]
        public readonly ?string $subject = null,

        #[Assert\Range(min: 0, max: 3_600_000)]
        public readonly ?int $durationMs = null,
    ) {
    }
}
