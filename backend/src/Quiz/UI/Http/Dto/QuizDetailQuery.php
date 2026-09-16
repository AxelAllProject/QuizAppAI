<?php

namespace App\Quiz\UI\Http\Dto;

class QuizDetailQuery
{
    public function __construct(
        /** Inclure les bonnes réponses (mode édition) : ignoré si l'on ne peut pas modifier le quiz. */
        public readonly bool $withAnswers = false,
    ) {
    }
}
