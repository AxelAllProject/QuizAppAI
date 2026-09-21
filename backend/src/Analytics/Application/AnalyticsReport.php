<?php

namespace App\Analytics\Application;

use App\Analytics\Domain\Model\AnalyticsEvent;
use App\Analytics\Domain\Repository\AnalyticsEventRepository;
use Psr\Clock\ClockInterface;

/**
 * Tableau de bord des statistiques : audience, écrans consultés, actions clés et usage
 * pédagogique, sur une fenêtre de temps choisie par l'administrateur.
 *
 * Tout est agrégé en base. Aucune méthode ne charge d'événements en mémoire : la table
 * grossit d'une ligne par page vue, elle ne se parcourt pas en PHP.
 */
class AnalyticsReport
{
    /** Fenêtres proposées dans le back-office. */
    public const WINDOWS = [7, 30, 90, 365];

    public function __construct(
        private readonly AnalyticsEventRepository $events,
        private readonly QuizCatalog $quizzes,
        private readonly ClockInterface $clock,
    ) {
    }

    /** Rapport complet sur les $days derniers jours. */
    public function forLastDays(int $days): array
    {
        $now = $this->clock->now();
        $since = $now->modify(sprintf('-%d days', $days));

        return [
            'days' => $days,
            'generatedAt' => $now->format(\DateTimeInterface::ATOM),
            'audience' => $this->audience($now, $since),
            'pages' => $this->events->topPaths($since),
            'actions' => $this->actions($since),
            'ai' => $this->ai($since),
            'learning' => $this->learning($since),
        ];
    }

    /** Fréquentation : comptes actifs sur plusieurs horizons, inscriptions, courbe et rôles. */
    private function audience(\DateTimeImmutable $now, \DateTimeImmutable $since): array
    {
        return [
            'activeToday' => $this->events->activeAccountsSince($now->modify('today')),
            'activeLast7Days' => $this->events->activeAccountsSince($now->modify('-7 days')),
            'activeLast30Days' => $this->events->activeAccountsSince($now->modify('-30 days')),
            'activeOnWindow' => $this->events->activeAccountsSince($since),
            'signups' => $this->events->countEvents(AnalyticsEvent::SIGNUP, $since),
            'dailyActive' => $this->daily($this->events->dailyActiveAccounts($since), $since, $now),
            'dailySignups' => $this->daily($this->events->dailyCounts(AnalyticsEvent::SIGNUP, $since), $since, $now),
            'dailyPageViews' => $this->daily($this->events->dailyCounts(AnalyticsEvent::PAGE_VIEW, $since), $since, $now),
            'byRole' => $this->events->countsByRole(AnalyticsEvent::PAGE_VIEW, $since),
        ];
    }

    /**
     * Série complète, un point par jour, zéros compris. Le remplissage est fait ici et non
     * dans le navigateur : les dates viennent du fuseau du serveur, et lui seul le connaît —
     * un client à Paris reconstruirait des clés décalées d'un jour sur une base en UTC.
     *
     * @param array<string, int> $counts nombre par date (AAAA-MM-JJ), jours creux absents
     *
     * @return list<array{date: string, value: int}>
     */
    private function daily(array $counts, \DateTimeImmutable $since, \DateTimeImmutable $now): array
    {
        $series = [];

        for ($day = $since->modify('today'); $day <= $now; $day = $day->modify('+1 day')) {
            $date = $day->format('Y-m-d');
            $series[] = ['date' => $date, 'value' => $counts[$date] ?? 0];
        }

        return $series;
    }

    /** Compteurs des actions clés sur la fenêtre. */
    private function actions(\DateTimeImmutable $since): array
    {
        $names = [
            'logins' => AnalyticsEvent::LOGIN,
            'quizzesCreated' => AnalyticsEvent::QUIZ_CREATED,
            'quizzesStarted' => AnalyticsEvent::QUIZ_STARTED,
            'quizzesFinished' => AnalyticsEvent::QUIZ_FINISHED,
            'liveGamesHosted' => AnalyticsEvent::LIVE_HOSTED,
            'liveGamesJoined' => AnalyticsEvent::LIVE_JOINED,
            'rgpdExports' => AnalyticsEvent::RGPD_EXPORT,
        ];

        return array_map(fn (string $name) => $this->events->countEvents($name, $since), $names);
    }

    /**
     * Génération par IA : volume et taux de réussite. Le taux ne se calcule que sur des
     * générations réellement tentées — un quota épuisé ou un droit refusé n'est pas compté
     * comme un échec, sinon le chiffre ne dirait plus rien de la qualité du service.
     */
    private function ai(\DateTimeImmutable $since): array
    {
        $succeeded = $this->events->countEvents(AnalyticsEvent::AI_GENERATION, $since, AnalyticsEvent::OUTCOME_SUCCESS);
        $failed = $this->events->countEvents(AnalyticsEvent::AI_GENERATION, $since, AnalyticsEvent::OUTCOME_FAILURE);
        $attempted = $succeeded + $failed;

        return [
            'attempted' => $attempted,
            'succeeded' => $succeeded,
            'failed' => $failed,
            'successRate' => $attempted > 0 ? (int) round($succeeded / $attempted * 100) : null,
        ];
    }

    /**
     * Usage pédagogique : quiz les plus joués, matières les plus travaillées, abandon, et
     * quiz que personne n'a encore ouverts.
     */
    private function learning(\DateTimeImmutable $since): array
    {
        $started = $this->events->countEvents(AnalyticsEvent::QUIZ_STARTED, $since);
        $finished = $this->events->countEvents(AnalyticsEvent::QUIZ_FINISHED, $since);
        $plays = $this->events->topSubjects(AnalyticsEvent::QUIZ_FINISHED, $since);
        $described = $this->quizzes->describe(array_map('intval', array_keys($plays)));

        return [
            'topQuizzes' => $this->rank($plays, $described),
            'topCategories' => $this->categoriesOf($plays, $described),
            // Un quiz ouvert mais jamais terminé : l'écart entre les deux compteurs. Il peut
            // dépasser 100 % si des parties commencées avant la fenêtre s'y terminent, d'où le plafond.
            'abandonRate' => $started > 0 ? max(0, min(100, (int) round(($started - $finished) / $started * 100))) : null,
            'neverPlayedQuizzes' => max(0, $this->quizzes->countAll() - count($plays)),
        ];
    }

    /**
     * @param array<string, int>                                 $plays     nombre de parties par identifiant de quiz
     * @param array<int, array{title: string, category: string}> $described fiches des quiz encore publiés
     *
     * @return list<array{id: int, title: string, plays: int}>
     */
    private function rank(array $plays, array $described): array
    {
        $ranked = [];

        foreach ($plays as $id => $count) {
            // Un quiz supprimé depuis garde ses parties dans les compteurs, mais n'a plus de fiche.
            $ranked[] = ['id' => (int) $id, 'title' => $described[(int) $id]['title'] ?? 'quiz supprimé', 'plays' => $count];
        }

        return $ranked;
    }

    /**
     * Regroupe les parties par matière. La matière est lue sur le quiz, pas recopiée dans
     * l'événement : le classement reste juste si un quiz change de matière après coup.
     *
     * @param array<string, int>                                 $plays     nombre de parties par identifiant de quiz
     * @param array<int, array{title: string, category: string}> $described fiches des quiz encore publiés
     *
     * @return array<string, int>
     */
    private function categoriesOf(array $plays, array $described): array
    {
        $byCategory = [];

        foreach ($plays as $id => $count) {
            if ($category = $described[(int) $id]['category'] ?? null) {
                $byCategory[$category] = ($byCategory[$category] ?? 0) + $count;
            }
        }

        arsort($byCategory);

        return $byCategory;
    }
}
