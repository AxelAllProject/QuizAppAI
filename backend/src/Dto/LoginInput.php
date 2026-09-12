<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class LoginInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Indique ton adresse e-mail.')]
        public readonly string $email = '',

        #[\SensitiveParameter]
        #[Assert\NotBlank(message: 'Indique ton mot de passe.')]
        public readonly string $password = '',
    ) {
    }
}
