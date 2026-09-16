<?php

namespace App\Ai\UI\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Clé IA saisie dans « Mon compte ». */
class RedeemAiKeyInput
{
    public function __construct(
        #[\SensitiveParameter]
        #[Assert\NotBlank(message: 'Saisis la clé IA.')]
        public readonly string $key = '',
    ) {
    }
}
