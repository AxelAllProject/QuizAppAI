<?php

namespace App\Game\UI\Http\Dto;

/** Paramètres de l'historique des parties (?all=1). */
class HistoryFilter
{
    public function __construct(
        /** Toutes les parties plutôt que les siennes : ignoré si l'on n'est pas administrateur. */
        public readonly bool $all = false,
    ) {
    }
}
