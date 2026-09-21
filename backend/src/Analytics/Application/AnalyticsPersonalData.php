<?php

namespace App\Analytics\Application;

use App\Analytics\Domain\Repository\AnalyticsEventRepository;
use App\Identity\Application\PersonalDataEraser;
use App\Identity\Application\PersonalDataExporter;
use App\Identity\Domain\Model\User;

/**
 * Les mesures sont rattachées à un compte tant qu'il existe : elles lui sont donc
 * communicables (art. 15) et doivent disparaître avec lui (art. 17).
 *
 * À l'effacement, les lignes survivent mais plus rien ne les y rattache : le lien est
 * coupé, seul reste le rôle déjà recopié. Les événements deviennent alors de simples
 * compteurs anonymes — ce qu'ils auraient été dès le départ si l'on n'avait pas eu
 * besoin de distinguer les personnes actives.
 */
class AnalyticsPersonalData implements PersonalDataEraser, PersonalDataExporter
{
    public function __construct(private readonly AnalyticsEventRepository $events)
    {
    }

    /**
     * Un relevé par type de fait plutôt que la liste des pages vues une à une :
     * c'est la même information, sans reconstituer l'historique de navigation de
     * quelqu'un dans un fichier qu'il pourrait être amené à transmettre.
     */
    public function export(User $user): array
    {
        return ['mesuresDUsage' => $this->events->summaryFor($user)];
    }

    /** Détache du compte supprimé tous les événements qui le désignaient. */
    public function erase(User $user): void
    {
        $this->events->detachUser($user);
    }
}
