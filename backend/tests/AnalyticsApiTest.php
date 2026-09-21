<?php

namespace App\Tests;

use App\Analytics\Domain\Model\AnalyticsEvent;

/** Mesure d'usage : collecte des pages vues et tableau de bord du back-office. */
class AnalyticsApiTest extends ApiTestCase
{
    public function testTheDashboardIsReservedToAdmins(): void
    {
        $this->request('GET', '/api/analytics', as: 'axel');
        $this->assertResponseStatusCodeSame(403);

        $this->request('GET', '/api/analytics', as: 'prof.martin');
        $this->assertResponseStatusCodeSame(403);

        $this->request('GET', '/api/analytics', as: $this->admin());
        $this->assertResponseIsSuccessful();
    }

    public function testPageViewsAreCountedWithTheirPathAndVisitors(): void
    {
        $this->view('/', as: 'axel');
        $this->view('/', as: 'lea');
        $this->view('/historique', as: 'axel');

        $pages = $this->request('GET', '/api/analytics', as: $this->admin())['pages'];

        $this->assertSame('/', $pages[0]['path']);
        $this->assertSame(2, $pages[0]['views']);
        $this->assertSame(2, $pages[0]['visitors']);
    }

    /** Sans normalisation, chaque quiz ferait sa propre ligne et le classement ne dirait rien. */
    public function testPathsAreNormalisedSoThatOneScreenIsOneLine(): void
    {
        foreach ([7, 42, 128] as $id) {
            $this->view("/quiz/$id", as: 'axel');
        }

        $pages = $this->request('GET', '/api/analytics', as: $this->admin())['pages'];

        $this->assertCount(1, $pages);
        $this->assertSame('/quiz/:id', $pages[0]['path']);
        $this->assertSame(3, $pages[0]['views']);
    }

    /** La page d'accueil et la connexion se visitent avant d'avoir un compte. */
    public function testAVisitorWithoutAnAccountIsCounted(): void
    {
        $this->request('POST', '/api/events', ['name' => AnalyticsEvent::PAGE_VIEW, 'path' => '/login']);
        $this->assertResponseStatusCodeSame(204);

        $report = $this->request('GET', '/api/analytics', as: $this->admin())['audience'];

        $this->assertSame(1, $report['byRole']['anonyme']);
    }

    /** Le frontend ne déclare que ce qu'il est le mieux placé pour savoir : le reste est refusé. */
    public function testTheBrowserCannotDeclareArbitraryEvents(): void
    {
        $this->request('POST', '/api/events', ['name' => AnalyticsEvent::SIGNUP], as: 'axel');
        $this->assertResponseStatusCodeSame(422);

        $this->request('POST', '/api/events', ['name' => 'nimporte_quoi'], as: 'axel');
        $this->assertResponseStatusCodeSame(422);
    }

    /** Les actions clés sont mesurées côté serveur : le navigateur n'a rien à envoyer. */
    public function testKeyActionsAreRecordedByTheServerItself(): void
    {
        $quiz = $this->createQuiz();
        $this->request('POST', "/api/quizzes/$quiz/sessions", ['answers' => [1, 0], 'durationSeconds' => 30], as: 'axel');
        $this->assertResponseIsSuccessful();

        $actions = $this->request('GET', '/api/analytics', as: $this->admin())['actions'];

        $this->assertSame(1, $actions['quizzesCreated']);
        $this->assertSame(1, $actions['quizzesFinished']);
    }

    /** Une inscription refusée n'est pas une inscription : seuls les succès comptent. */
    public function testAFailedActionIsNotCounted(): void
    {
        $this->request('POST', '/api/register', ['email' => 'pas-une-adresse', 'name' => 'x', 'password' => 'court', 'consent' => false]);
        $this->assertResponseStatusCodeSame(422);

        $before = $this->request('GET', '/api/analytics', as: $this->admin())['audience']['signups'];

        $this->request('POST', '/api/register', [
            'email' => 'nouvelle@exemple.test',
            'name' => 'nouvelle',
            'password' => self::PASSWORD,
            'consent' => true,
        ], server: ['REMOTE_ADDR' => '203.0.113.42']);
        $this->assertResponseStatusCodeSame(201);

        $this->assertSame($before + 1, $this->request('GET', '/api/analytics', as: $this->admin())['audience']['signups']);
    }

    /** Le palmarès affiche des titres, que les mesures ne stockent pas. */
    public function testTheMostPlayedQuizzesAreListedWithTheirTitleAndCategory(): void
    {
        $quiz = $this->createQuiz();
        $this->request('POST', "/api/quizzes/$quiz/sessions", ['answers' => [1, 0], 'durationSeconds' => 30], as: 'axel');
        $this->request('POST', "/api/quizzes/$quiz/sessions", ['answers' => [1, 0], 'durationSeconds' => 30], as: 'lea');

        $learning = $this->request('GET', '/api/analytics', as: $this->admin())['learning'];

        $this->assertSame([['id' => $quiz, 'title' => 'Quiz de test', 'plays' => 2]], $learning['topQuizzes']);
        $this->assertSame(['Divers' => 2], $learning['topCategories']);
        $this->assertSame(0, $learning['neverPlayedQuizzes']);
    }

    /** Un quiz ouvert mais jamais terminé : l'écart entre les deux compteurs. */
    public function testTheAbandonRateComparesStartedAndFinishedQuizzes(): void
    {
        $quiz = $this->createQuiz();

        foreach (['axel', 'lea', 'hugo', 'nina'] as $player) {
            $this->request('POST', '/api/events', ['name' => AnalyticsEvent::QUIZ_STARTED, 'subject' => (string) $quiz], as: $player);
        }

        $this->request('POST', "/api/quizzes/$quiz/sessions", ['answers' => [1, 0], 'durationSeconds' => 30], as: 'axel');

        $learning = $this->request('GET', '/api/analytics', as: $this->admin())['learning'];

        $this->assertSame(75, $learning['abandonRate']);
    }

    public function testTheWindowOnlyAcceptsTheOfferedRanges(): void
    {
        $this->assertSame(7, $this->request('GET', '/api/analytics?days=7', as: $this->admin())['days']);

        $this->request('GET', '/api/analytics?days=3', as: $this->admin());
        $this->assertResponseStatusCodeSame(422);
    }

    /** Sans génération tentée, le taux n'existe pas : mieux vaut rien que 0 %. */
    public function testTheAiSuccessRateIsNullWhenNothingWasGenerated(): void
    {
        $ai = $this->request('GET', '/api/analytics', as: $this->admin())['ai'];

        $this->assertSame(0, $ai['attempted']);
        $this->assertNull($ai['successRate']);
    }

    /** Une mesure ne se rattache plus à personne une fois le compte supprimé. */
    public function testEventsSurviveAccountDeletionWithoutNamingAnyone(): void
    {
        $this->view('/', as: 'axel');

        $this->request('DELETE', '/api/me', ['password' => self::PASSWORD], as: 'axel');
        $this->assertResponseStatusCodeSame(204);

        $report = $this->request('GET', '/api/analytics', as: $this->admin());

        // La page vue reste comptée, mais plus aucun compte actif ne lui correspond.
        $this->assertSame(1, $report['pages'][0]['views']);
        $this->assertSame(0, $report['pages'][0]['visitors']);
    }

    /** Déclare une page vue depuis le navigateur. */
    private function view(string $path, string $as): void
    {
        $this->request('POST', '/api/events', ['name' => AnalyticsEvent::PAGE_VIEW, 'path' => $path], as: $as);
        $this->assertResponseStatusCodeSame(204);
    }
}
