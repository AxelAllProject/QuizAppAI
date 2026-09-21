<?php

namespace App\Analytics\Domain\Repository;

use App\Analytics\Domain\Model\AnalyticsEvent;
use App\Identity\Domain\Model\User;

/**
 * Accès aux événements de mesure. Toutes les agrégations se font en base : le back-office
 * doit rester rapide quand la table compte des centaines de milliers de lignes.
 */
interface AnalyticsEventRepository
{
    /** Prépare l'enregistrement d'un événement (écrit au prochain flush). */
    public function add(AnalyticsEvent $event): void;

    /** Nombre de comptes distincts ayant agi depuis cette date. */
    public function activeAccountsSince(\DateTimeImmutable $since): int;

    /**
     * Nombre d'événements portant ce nom, jour par jour, depuis cette date.
     *
     * @return array<string, int> nombre par date (AAAA-MM-JJ), jours sans activité omis
     */
    public function dailyCounts(string $name, \DateTimeImmutable $since): array;

    /**
     * Comptes distincts actifs jour par jour depuis cette date.
     *
     * @return array<string, int> nombre par date (AAAA-MM-JJ)
     */
    public function dailyActiveAccounts(\DateTimeImmutable $since): array;

    /**
     * Répartition des événements par rôle depuis cette date.
     *
     * @return array<string, int> nombre par rôle
     */
    public function countsByRole(string $name, \DateTimeImmutable $since): array;

    /**
     * Écrans les plus consultés depuis cette date, avec leur durée moyenne.
     *
     * @return list<array{path: string, views: int, visitors: int, averageMs: int}>
     */
    public function topPaths(\DateTimeImmutable $since, int $limit = 10): array;

    /**
     * Sujets les plus fréquents pour un événement (quiz les plus joués, par exemple).
     *
     * @return array<string, int> nombre par sujet, du plus fréquent au moins fréquent
     */
    public function topSubjects(string $name, \DateTimeImmutable $since, int $limit = 10): array;

    /** Nombre total d'événements portant ce nom depuis cette date (issue facultative). */
    public function countEvents(string $name, \DateTimeImmutable $since, ?string $outcome = null): int;

    /**
     * Ce que les mesures retiennent d'une personne, pour son export RGPD : par type de fait,
     * combien de fois et entre quelles dates.
     *
     * @return list<array{event: string, count: int, firstAt: string, lastAt: string}>
     */
    public function summaryFor(User $user): array;

    /** Supprime les événements antérieurs à cette date et renvoie leur nombre. */
    public function deleteBefore(\DateTimeImmutable $limit): int;

    /** Détache les événements d'un compte supprimé, sans perdre les compteurs. */
    public function detachUser(User $user): void;
}
