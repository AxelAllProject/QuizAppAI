<?php

namespace App\Dto;

class PlayInput
{
    /**
     * @param array<int|string, int|null> $answers index du choix retenu, par identifiant de question
     */
    public function __construct(
        public readonly array $answers = [],
        public readonly ?int $durationSeconds = null,
    ) {
    }
}
