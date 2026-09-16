<?php

namespace App\Quiz\UI\Http\Dto;

/** Paramètres de l'affichage d'un quiz (?withAnswers=1). */
class QuizDetailQuery
{
    public function __construct(
        /** Inclure les bonnes réponses (mode édition) : ignoré si l'on ne peut pas modifier le quiz. */
        public readonly bool $withAnswers = false,
    ) {
    }
}
