<?php

namespace App\Identity\UI\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Identifiants envoyés à la connexion. */
class LoginInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Indique ton adresse e-mail.')]
        public readonly string $email = '',

        #[\SensitiveParameter]
        #[Assert\NotBlank(message: 'Indique ton mot de passe.')]
        // Au-delà, les hacheurs de Symfony lèvent une exception au lieu de répondre « incorrect ».
        #[Assert\Length(max: 4096)]
        public readonly string $password = '',
    ) {
    }
}
