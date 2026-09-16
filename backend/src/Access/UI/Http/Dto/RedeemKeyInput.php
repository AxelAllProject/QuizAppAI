<?php

namespace App\Access\UI\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class RedeemKeyInput
{
    public function __construct(
        #[\SensitiveParameter]
        #[Assert\NotBlank(message: 'Saisis la clé d’accès.')]
        public readonly string $key = '',
    ) {
    }
}
