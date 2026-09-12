<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Stocke les illustrations de quiz dans public/uploads, sous un nom aléatoire.
 * Les quiz ne peuvent référencer que ces chemins-là : pas d'image hébergée
 * ailleurs, donc aucune requête vers un tiers quand un joueur affiche un quiz.
 */
class ImageUploader
{
    public const PATH_PATTERN = '#^/uploads/[a-f0-9]{32}\.(jpg|png|gif|webp)$#';
    public const MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(
        #[Autowire('%app.upload_dir%')]
        private readonly string $directory,
    ) {
    }

    /** @return string chemin public, à enregistrer tel quel dans le quiz */
    public function store(UploadedFile $file): string
    {
        $name = sprintf('%s.%s', bin2hex(random_bytes(16)), $file->guessExtension());
        $file->move($this->directory, $name);

        return '/uploads/'.$name;
    }
}
