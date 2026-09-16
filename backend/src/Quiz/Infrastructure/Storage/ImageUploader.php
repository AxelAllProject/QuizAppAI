<?php

namespace App\Quiz\Infrastructure\Storage;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Stocke les illustrations de quiz dans public/uploads, sous un nom aléatoire
 * correspondant à QuizImage::PATH_PATTERN.
 */
class ImageUploader
{
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
