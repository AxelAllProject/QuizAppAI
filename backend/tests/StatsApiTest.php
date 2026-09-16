<?php

namespace App\Tests;

/** Compteurs agrégés en base : tableau de bord et annuaire des comptes. */
class StatsApiTest extends ApiTestCase
{
    public function testDashboardCountersAggregateEveryGame(): void
    {
        $quizId = $this->createQuiz();

        // bob : 2/2 puis 1/2 ; chloe : 0/2 → 3 bonnes réponses sur 6 questions.
        $this->play($quizId, 'bob', [1, 0]);
        $this->play($quizId, 'bob', [1, 1]);
        $this->play($quizId, 'chloe', [0, 1]);

        $stats = $this->request('GET', '/api/stats', as: 'bob');

        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $stats['quizCount']);
        $this->assertSame(3, $stats['sessionCount']);
        $this->assertSame(2, $stats['playerCount']);
        $this->assertSame(50, $stats['globalAccuracy']);
    }

    public function testTheGeneralLeaderboardIsSortedByAccuracyThenGamesAndLimitedInSql(): void
    {
        $quizId = $this->createQuiz();

        $this->play($quizId, 'bob', [1, 0]);   // 2/2
        $this->play($quizId, 'chloe', [1, 1]); // 1/2
        $this->play($quizId, 'chloe', [1, 0]); // 2/2 → 3/4
        $this->play($quizId, 'dan', [1, 1]);   // 1/2
        $this->play($quizId, 'dan', [1, 1]);   // 1/2 → 2/4, mais plus de parties qu'eve
        $this->play($quizId, 'eve', [1, 1]);   // 1/2

        $leaderboard = $this->request('GET', '/api/stats', as: 'bob')['leaderboard'];

        $this->assertSame(['bob', 'chloe', 'dan', 'eve'], array_column($leaderboard, 'player'));
        $this->assertSame([100, 75, 50, 50], array_column($leaderboard, 'accuracy'));
        $this->assertSame(2, $leaderboard[2]['games']);

        for ($i = 0; $i < 10; ++$i) {
            $this->play($quizId, 'joueur'.$i, [0, 1]);
        }
        $this->assertCount(10, $this->request('GET', '/api/stats', as: 'bob')['leaderboard'], 'Le classement général ne garde que les 10 premiers.');
    }

    public function testDashboardCountersStartAtZero(): void
    {
        $stats = $this->request('GET', '/api/stats', as: 'bob');

        $this->assertSame(0, $stats['sessionCount']);
        $this->assertSame(0, $stats['playerCount']);
        $this->assertSame(0, $stats['globalAccuracy']);
    }

    public function testTheDirectoryShowsEachAccountsGamesAndAccuracy(): void
    {
        $quizId = $this->createQuiz();
        $this->play($quizId, 'bob', [1, 0]);
        $this->play($quizId, 'bob', [0, 1]);
        $this->account('chloe');

        $accounts = array_column($this->request('GET', '/api/users', as: $this->admin()), null, 'name');

        $this->assertSame(2, $accounts['bob']['games']);
        $this->assertSame(50, $accounts['bob']['accuracy']);
        $this->assertSame(0, $accounts['chloe']['games']);
        $this->assertNull($accounts['chloe']['accuracy']);
    }

    /** @param list<int> $choices choix par question, dans l'ordre du quiz */
    private function play(int $quizId, string $player, array $choices): void
    {
        $questions = $this->request('GET', '/api/quizzes/'.$quizId, as: $player)['questions'];
        $answers = array_combine(array_column($questions, 'id'), $choices);

        $this->request('POST', '/api/quizzes/'.$quizId.'/sessions', ['answers' => $answers], as: $player);
        $this->assertResponseStatusCodeSame(201);
    }
}
