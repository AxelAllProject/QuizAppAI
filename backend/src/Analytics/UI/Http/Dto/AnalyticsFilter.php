<?php

namespace App\Analytics\UI\Http\Dto;

use App\Analytics\Application\AnalyticsReport;
use Symfony\Component\Validator\Constraints as Assert;

/** Fenêtre de temps du tableau de bord, en jours. */
class AnalyticsFilter
{
    public function __construct(
        #[Assert\Choice(choices: AnalyticsReport::WINDOWS, message: 'Fenêtre de temps non prise en charge.')]
        public readonly int $days = 30,
    ) {
    }
}
