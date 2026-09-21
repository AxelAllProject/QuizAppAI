<?php

namespace App\Identity\UI\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * « Mon compte » : nouveaux e-mail, pseudo ou mot de passe. Les champs laissés à null
 * ne sont pas touchés ; `password` est le mot de passe actuel, exigé dans tous les cas.
 */
class UpdateProfileInput
{
    public function __construct(
        #[\SensitiveParameter]
        #[Assert\NotBlank(message: 'Confirme avec ton mot de passe actuel.')]
        public readonly string $password = '',

        #[Assert\Email(message: 'Cette adresse e-mail n’est pas valide.')]
        #[Assert\Length(max: 180)]
        public readonly ?string $email = null,

        #[Assert\Length(min: 2, max: 40, minMessage: 'Le pseudo doit faire au moins {{ limit }} caractères.', maxMessage: 'Le pseudo ne doit pas dépasser {{ limit }} caractères.')]
        #[Assert\Regex(pattern: '/^[\p{L}\p{N}._ -]+$/u', message: 'Le pseudo ne peut contenir que des lettres, chiffres, espaces, points, tirets et soulignés.')]
        public readonly ?string $name = null,

        #[\SensitiveParameter]
        #[Assert\Length(min: 8, max: 4096, minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères.')]
        public readonly ?string $newPassword = null,
    ) {
    }

    /** Indique si le formulaire demande au moins un changement. */
    public function isEmpty(): bool
    {
        return null === $this->email && null === $this->name && null === $this->newPassword;
    }
}
