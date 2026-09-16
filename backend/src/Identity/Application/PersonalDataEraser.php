<?php

namespace App\Identity\Application;

use App\Identity\Domain\Model\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Droit à l'effacement (RGPD, art. 17) : chaque contexte efface ou anonymise ce qu'il
 * détient sur une personne. Appelé dans la transaction d'AccountEraser, avant la
 * suppression du compte : ne pas flusher.
 */
#[AutoconfigureTag]
interface PersonalDataEraser
{
    /** Pseudo affiché à la place d'un compte supprimé. */
    public const ANONYMOUS = 'compte supprimé';

    public function erase(User $user): void;
}
