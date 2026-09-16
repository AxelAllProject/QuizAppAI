<?php

namespace App\Tests;

use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class LiveGameApiTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testOnlyTeachersCanHostAGame(): void
    {
        $quizId = $this->createQuiz();

        $this->request('POST', '/api/live-games', ['quizId' => $quizId], as: 'bob');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAFullGameRewardsSpeedAndEndsInEveryonesHistory(): void
    {
        $clock = static::mockTime();
        $pin = $this->hostGame();

        $lobby = $this->request('POST', "/api/live-games/$pin/join", as: 'bob');
        $this->assertResponseIsSuccessful();
        $this->assertSame('lobby', $lobby['status']);
        $this->request('POST', "/api/live-games/$pin/join", as: 'chloe');
        $this->assertSame(['bob', 'chloe'], $this->request('GET', "/api/live-games/$pin", as: 'prof.martin')['players']);

        // Question 1 : bob répond juste tout de suite, chloe se trompe au bout de 5 s.
        $question = $this->request('POST', "/api/live-games/$pin/next", as: 'prof.martin');
        $this->assertSame('question', $question['status']);
        $this->assertArrayNotHasKey('correctIndex', $question['question'], 'La bonne réponse ne sort pas avant la fin de la question.');

        $this->request('POST', "/api/live-games/$pin/answers", ['choiceIndex' => 1], as: 'bob');
        $this->assertResponseIsSuccessful();
        $this->request('POST', "/api/live-games/$pin/answers", ['choiceIndex' => 0], as: 'bob');
        $this->assertResponseStatusCodeSame(409, 'Une seule réponse par question.');

        $clock->sleep(5);
        $reveal = $this->request('POST', "/api/live-games/$pin/answers", ['choiceIndex' => 0], as: 'chloe');

        $this->assertSame('reveal', $reveal['status'], 'Tout le monde a répondu : inutile d’attendre la fin du chrono.');
        $this->assertSame(1, $reveal['question']['correctIndex']);
        $this->assertSame([1, 1], $reveal['distribution']);
        $this->assertSame(['correctIndex' => 0, 'correct' => false, 'points' => 0], ['correctIndex' => $reveal['me']['answer']['choiceIndex'], 'correct' => $reveal['me']['answer']['correct'], 'points' => $reveal['me']['answer']['points']]);
        $this->assertSame('bob', $reveal['leaderboard'][0]['nickname']);
        $this->assertSame(1000, $reveal['leaderboard'][0]['score']);

        // Question 2 : bob répond juste à mi-chrono, chloe laisse filer le temps.
        $this->request('POST', "/api/live-games/$pin/next", as: 'prof.martin');
        $clock->sleep(10);
        $this->request('POST', "/api/live-games/$pin/answers", ['choiceIndex' => 0], as: 'bob');
        $this->assertSame(1750, $this->request('GET', "/api/live-games/$pin", as: 'bob')['me']['score']);

        $clock->sleep(11);
        $this->assertSame('reveal', $this->request('GET', "/api/live-games/$pin", as: 'chloe')['status'], 'Le chrono écoulé clôt la question.');
        $this->request('POST', "/api/live-games/$pin/answers", ['choiceIndex' => 0], as: 'chloe');
        $this->assertResponseStatusCodeSame(409);

        $podium = $this->request('POST', "/api/live-games/$pin/next", as: 'prof.martin');
        $this->assertSame('finished', $podium['status']);
        $this->assertSame(['bob', 'chloe'], array_column($podium['leaderboard'], 'nickname'));

        $history = $this->request('GET', '/api/sessions', as: 'bob');
        $this->assertCount(1, $history);
        $this->assertSame(2, $history[0]['score']);
        $this->assertSame(2, $history[0]['total']);
    }

    public function testOnlyTheHostDrivesTheGame(): void
    {
        $pin = $this->hostGame();
        $this->request('POST', "/api/live-games/$pin/join", as: 'bob');

        $this->request('POST', "/api/live-games/$pin/next", as: 'bob');
        $this->assertResponseStatusCodeSame(403);

        $this->request('DELETE', "/api/live-games/$pin", as: 'bob');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testTheGameCannotStartWithoutPlayers(): void
    {
        $pin = $this->hostGame();

        $this->request('POST', "/api/live-games/$pin/next", as: 'prof.martin');

        $this->assertResponseStatusCodeSame(409);
    }

    public function testStrangersMustJoinBeforeSeeingTheGame(): void
    {
        $pin = $this->hostGame();

        $this->request('GET', "/api/live-games/$pin", as: 'curieux');
        $this->assertResponseStatusCodeSame(403);

        $this->request('POST', '/api/live-games/000000/join', as: 'curieux');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testSweepingPinsIsRateLimitedPerAccount(): void
    {
        $pin = $this->hostGame();
        $unknownPins = array_diff(array_map(static fn (int $i) => sprintf('%06d', $i), range(0, 10)), [$pin]);

        foreach (array_slice($unknownPins, 0, 10) as $unknown) {
            $this->request('POST', "/api/live-games/$unknown/join", as: 'curieux');
            $this->assertResponseStatusCodeSame(404);
        }

        // Une fois la limite atteinte, même un PIN valide est refusé : impossible de continuer à balayer.
        $this->request('POST', "/api/live-games/$pin/join", as: 'curieux');
        $this->assertResponseStatusCodeSame(429);
        $this->assertResponseHasHeader('Retry-After');

        $this->request('POST', "/api/live-games/$pin/join", as: 'eleve');
        $this->assertResponseIsSuccessful('Les erreurs d’un compte ne doivent pas bloquer les autres joueurs.');
    }

    public function testKnownPinsNeverCountTowardsTheLimit(): void
    {
        $pin = $this->hostGame();
        $this->request('POST', "/api/live-games/$pin/join", as: 'eleve');

        for ($i = 0; $i < 30; ++$i) {
            $this->request('GET', "/api/live-games/$pin", as: 'eleve');
        }

        $this->assertResponseIsSuccessful('Le polling d’une vraie partie (une requête par seconde) ne doit jamais être limité.');
    }

    public function testStoppingAGameClosesItToNewPlayers(): void
    {
        $pin = $this->hostGame();

        $stopped = $this->request('DELETE', "/api/live-games/$pin", as: 'prof.martin');
        $this->assertSame('finished', $stopped['status']);

        $this->request('POST', "/api/live-games/$pin/join", as: 'bob');
        $this->assertResponseStatusCodeSame(409);
    }

    public function testDeletingAnAccountRemovesItsLiveParticipations(): void
    {
        $pin = $this->hostGame();
        $this->request('POST', "/api/live-games/$pin/join", as: 'bob');

        $this->request('DELETE', '/api/me', ['password' => self::PASSWORD], as: 'bob');
        $this->assertResponseStatusCodeSame(204);

        $this->assertSame([], $this->request('GET', "/api/live-games/$pin", as: 'prof.martin')['players']);
    }

    /**
     * L'état est interrogé chaque seconde par chaque écran : son coût en requêtes SQL
     * ne doit pas grandir avec le nombre de joueurs.
     */
    public function testPollingTheStateCostsTheSameWhateverThePlayerCount(): void
    {
        $pin = $this->hostGame();
        $this->request('POST', "/api/live-games/$pin/join", as: 'bob');
        $this->request('POST', "/api/live-games/$pin/next", as: 'prof.martin');
        $this->request('POST', "/api/live-games/$pin/answers", ['choiceIndex' => 1], as: 'bob');
        $withOnePlayer = $this->queriesFor("/api/live-games/$pin", 'prof.martin');

        foreach (['chloe', 'dora', 'eve', 'farid'] as $name) {
            $this->request('POST', "/api/live-games/$pin/join", as: $name);
        }
        $withFivePlayers = $this->queriesFor("/api/live-games/$pin", 'prof.martin');

        $this->assertSame($withOnePlayer, $withFivePlayers, 'Une requête SQL par joueur à chaque interrogation.');
        $this->assertLessThanOrEqual(5, $withFivePlayers);
    }

    private function queriesFor(string $uri, string $as): int
    {
        $this->account($as);
        $queries = static::getContainer()->get('doctrine.debug_data_holder');
        $queries->reset();

        $this->request('GET', $uri, as: $as);
        $this->assertResponseIsSuccessful();

        return count($queries->getData()['default'] ?? []);
    }

    private function hostGame(): string
    {
        $quizId = $this->createQuiz('prof.martin');
        $game = $this->request('POST', '/api/live-games', ['quizId' => $quizId], as: 'prof.martin');
        $this->assertResponseStatusCodeSame(201);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $game['pin']);

        return $game['pin'];
    }
}
