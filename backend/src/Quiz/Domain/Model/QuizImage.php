<?php

namespace App\Quiz\Domain\Model;

/**
 * Illustrations de quiz : un quiz ne peut référencer que des images téléversées sur
 * le serveur, jamais une image hébergée ailleurs — aucune requête vers un tiers quand
 * un joueur affiche un quiz.
 */
final class QuizImage
{
    public const PATH_PATTERN = '#^/uploads/[a-f0-9]{32}\.(jpg|png|gif|webp)$#';
    public const MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    private function __construct()
    {
    }
}
