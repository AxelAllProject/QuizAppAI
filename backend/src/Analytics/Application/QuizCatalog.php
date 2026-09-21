<?php

namespace App\Analytics\Application;

/**
 * Port : les mesures ne retiennent que des identifiants de quiz, mais le tableau de bord
 * s'affiche avec des titres et des matières. Analytics ne connaît pas le contexte Quiz —
 * l'adaptateur, dans Infrastructure, est seul à faire le lien.
 */
interface QuizCatalog
{
    /**
     * Titre et matière de chaque quiz demandé (les identifiants inconnus sont absents :
     * un quiz supprimé garde ses parties dans les compteurs, mais n'a plus de fiche).
     *
     * @param list<int> $ids
     *
     * @return array<int, array{title: string, category: string}>
     */
    public function describe(array $ids): array;

    /** Nombre total de quiz publiés, pour repérer ceux que personne n'a encore joués. */
    public function countAll(): int;
}
