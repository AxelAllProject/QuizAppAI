<?php

namespace App\Identity\UI\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Formulaire d'inscription : e-mail, pseudo, mot de passe, consentement et clé d'accès facultative. */
class RegisterInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Indique ton adresse e-mail.')]
        #[Assert\Email(message: 'Cette adresse e-mail n’est pas valide.')]
        #[Assert\Length(max: 180)]
        public readonly string $email = '',

        #[Assert\NotBlank(message: 'Choisis un pseudo.')]
        #[Assert\Length(min: 2, max: 40, minMessage: 'Le pseudo doit faire au moins {{ limit }} caractères.', maxMessage: 'Le pseudo ne doit pas dépasser {{ limit }} caractères.')]
        #[Assert\Regex(pattern: '/^[\p{L}\p{N}._ -]+$/u', message: 'Le pseudo ne peut contenir que des lettres, chiffres, espaces, points, tirets et soulignés.')]
        public readonly string $name = '',

        #[\SensitiveParameter]
        #[Assert\Length(min: 8, max: 4096, minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères.')]
        public readonly string $password = '',

        #[Assert\IsTrue(message: 'Accepte la politique de confidentialité pour créer ton compte.')]
        public readonly bool $consent = false,

        #[\SensitiveParameter]
        public readonly ?string $accessKey = null,
    ) {
    }
}
