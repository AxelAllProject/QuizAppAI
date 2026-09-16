<?php

namespace App\Shared\Infrastructure\Persistence;

/**
 * Motif « contient » pour une recherche LIKE saisie par un utilisateur.
 *
 * Sans échappement, `%` et `_` tapés dans la recherche sont des jokers SQL : « 100% »
 * trouverait « 1000 », et « _ » tout ce qui a au moins un caractère. À utiliser avec
 * `LIKE :term ESCAPE '!'` (le `!` s'écrit sans les doubles échappements de `\` en DQL).
 */
final class LikePattern
{
    public const ESCAPE = "ESCAPE '!'";

    private function __construct()
    {
    }

    /** Motif qui trouve le texte n'importe où, en minuscules, jokers neutralisés. */
    public static function contains(string $term): string
    {
        return '%'.strtr(mb_strtolower(trim($term)), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
    }
}
