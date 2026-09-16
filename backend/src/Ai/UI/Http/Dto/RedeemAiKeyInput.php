<?php

namespace App\Ai\UI\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class RedeemAiKeyInput
{
    public function __construct(
        #[\SensitiveParameter]
        #[Assert\NotBlank(message: 'Saisis la clé IA.')]
        public readonly string $key = '',
    ) {
    }
}
