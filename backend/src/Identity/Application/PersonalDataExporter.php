<?php

namespace App\Identity\Application;

use App\Identity\Domain\Model\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Droit d'accès et à la portabilité (RGPD, art. 15 et 20) : chaque contexte exporte
 * lui-même les données qu'il détient sur une personne. L'ordre des sections suit la
 * priorité déclarée avec #[AsTaggedItem].
 */
#[AutoconfigureTag]
interface PersonalDataExporter
{
    /** @return array<string, mixed> une ou plusieurs sections de l'export, par nom */
    public function export(User $user): array;
}
