<?php

namespace App\Shared\Application;

class AdminKeyGenerator
{
    /**
     * Alphabet sans caractères ambigus (ni O/0, ni I/1) : la clé se dicte
     * et se retape sans se tromper.
     */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const GROUPS = 4;
    private const GROUP_SIZE = 4;

    /** Clé au format XXXX-XXXX-XXXX-XXXX, tirée d'une source cryptographique. */
    public function generate(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $groups = [];

        for ($group = 0; $group < self::GROUPS; ++$group) {
            $chars = '';

            for ($i = 0; $i < self::GROUP_SIZE; ++$i) {
                $chars .= self::ALPHABET[random_int(0, $max)];
            }

            $groups[] = $chars;
        }

        return implode('-', $groups);
    }
}
