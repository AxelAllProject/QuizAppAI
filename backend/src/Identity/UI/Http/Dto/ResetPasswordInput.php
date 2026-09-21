<?php

namespace App\Identity\UI\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** « Mot de passe oublié » : le jeton reçu par e-mail et le nouveau mot de passe. */
class ResetPasswordInput
{
    public function __construct(
        #[\SensitiveParameter]
        #[Assert\NotBlank(message: 'Lien de réinitialisation incomplet.')]
        public readonly string $token = '',

        #[\SensitiveParameter]
        #[Assert\NotBlank(message: 'Choisis un nouveau mot de passe.')]
        #[Assert\Length(min: 8, max: 4096, minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères.')]
        public readonly string $password = '',
    ) {
    }
}
