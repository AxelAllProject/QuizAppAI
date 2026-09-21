<?php

namespace App\Analytics\Application;

use App\Analytics\Domain\Repository\AnalyticsEventRepository;

/**
 * Durée de conservation des mesures (RGPD, art. 5-1-e). Un an : de quoi comparer une
 * rentrée à la précédente, pas davantage. Au-delà, les lignes sont supprimées, pas archivées.
 */
class PurgeAnalytics
{
    public const RETENTION = '-1 year';

    public function __construct(private readonly AnalyticsEventRepository $events)
    {
    }

    /** Supprime les mesures arrivées en fin de conservation et renvoie leur nombre. */
    public function purge(\DateTimeImmutable $now): int
    {
        return $this->events->deleteBefore($now->modify(self::RETENTION));
    }
}
