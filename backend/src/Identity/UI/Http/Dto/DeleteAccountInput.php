<?php

namespace App\Identity\UI\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class DeleteAccountInput
{
    public function __construct(
        #[\SensitiveParameter]
        #[Assert\NotBlank(message: 'Confirme avec ton mot de passe.')]
        public readonly string $password = '',
    ) {
    }
}
