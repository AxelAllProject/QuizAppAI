<?php

namespace App\Identity\UI\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** « Mot de passe oublié » : l'adresse à laquelle envoyer le lien. */
class ForgotPasswordInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Indique ton adresse e-mail.')]
        #[Assert\Email(message: 'Cette adresse e-mail n’est pas valide.')]
        #[Assert\Length(max: 180)]
        public readonly string $email = '',
    ) {
    }
}
