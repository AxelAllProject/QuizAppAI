<?php

namespace App\Tests;

use App\Quiz\Application\Dto\QuestionInput;
use App\Quiz\Application\Dto\QuizInput;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class QuizApiTest extends ApiTestCase
{
    /** PNG de 1 × 1 pixel. */
    private const PIXEL_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function testOnlyTeachersCanCreateAQuiz(): void
    {
        $this->request('POST', '/api/quizzes', $this->quizPayload(), as: 'bob');
        $this->assertResponseStatusCodeSame(403);

        $this->account('prof.martin', 'prof');
        $quiz = $this->request('POST', '/api/quizzes', $this->quizPayload(), as: 'prof.martin');
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('prof.martin', $quiz['author']);
        $this->assertCount(2, $quiz['questions']);
    }

    public function testCreateRejectsQuizWithoutQuestion(): void
    {
        $this->request('POST', '/api/quizzes', ['title' => 'Vide', 'questions' => []], as: $this->admin());

        $this->assertResponseStatusCodeSame(422);
    }

    public function testCreateRejectsCorrectIndexOutOfRange(): void
    {
        $this->request('POST', '/api/quizzes', $this->quizPayload([
            'questions' => [['text' => 'Q ?', 'choices' => ['a', 'b'], 'correctIndex' => 7]],
        ]), as: $this->admin());

        $this->assertResponseStatusCodeSame(422);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function oversizedQuizzes(): iterable
    {
        $question = ['text' => 'Q ?', 'choices' => ['a', 'b'], 'correctIndex' => 0];

        yield 'trop de questions' => [['questions' => array_fill(0, QuizInput::MAX_QUESTIONS + 1, $question)]];
        yield 'description trop longue' => [['description' => str_repeat('d', QuizInput::MAX_DESCRIPTION_LENGTH + 1)]];
        yield 'intitulé trop long' => [['questions' => [['text' => str_repeat('q', QuestionInput::MAX_TEXT_LENGTH + 1)] + $question]]];
        yield 'réponse trop longue' => [['questions' => [['choices' => ['a', str_repeat('b', QuestionInput::MAX_CHOICE_LENGTH + 1)]] + $question]]];
        yield 'explication trop longue' => [['questions' => [['explanation' => str_repeat('e', QuestionInput::MAX_EXPLANATION_LENGTH + 1)] + $question]]];
    }

    #[DataProvider('oversizedQuizzes')]
    public function testCreateRejectsOversizedQuizzes(array $overrides): void
    {
        $this->request('POST', '/api/quizzes', $this->quizPayload($overrides), as: $this->admin());

        $this->assertResponseStatusCodeSame(422, 'Un quiz sans borne de taille pourrait remplir la base en un seul envoi.');
    }

    public function testAQuizAtTheSizeLimitsIsAccepted(): void
    {
        $question = [
            'text' => str_repeat('q', QuestionInput::MAX_TEXT_LENGTH),
            'choices' => ['a', str_repeat('b', QuestionInput::MAX_CHOICE_LENGTH)],
            'correctIndex' => 0,
            'explanation' => str_repeat('e', QuestionInput::MAX_EXPLANATION_LENGTH),
        ];

        $quiz = $this->request('POST', '/api/quizzes', $this->quizPayload([
            'description' => str_repeat('d', QuizInput::MAX_DESCRIPTION_LENGTH),
            'questions' => array_fill(0, QuizInput::MAX_QUESTIONS, $question),
        ]), as: $this->admin());

        $this->assertResponseStatusCodeSame(201);
        $this->assertCount(QuizInput::MAX_QUESTIONS, $quiz['questions']);
    }

    public function testTheLibraryCostsTheSameNumberOfQueriesWhateverTheQuizCount(): void
    {
        $this->createQuiz();
        $this->account('bob');
        $withOneQuiz = $this->countQueries('/api/quizzes', 'bob');
        $this->assertSame(2, $this->request('GET', '/api/quizzes', as: 'bob')[0]['questionCount']);

        for ($i = 0; $i < 4; ++$i) {
            $this->request('POST', '/api/quizzes', $this->quizPayload(), as: 'prof.martin');
        }

        $this->assertSame($withOneQuiz, $this->countQueries('/api/quizzes', 'bob'), 'Chaque quiz ne doit pas coûter une requête de plus (N+1 sur les questions).');
    }

    public function testSearchTreatsPercentAndUnderscoreLiterally(): void
    {
        $this->account('prof.martin', 'prof');
        foreach (['Réussir à 100% en maths', 'Les 1000 mots', 'snake_case en Python', 'snakecase'] as $title) {
            $this->request('POST', '/api/quizzes', $this->quizPayload(['title' => $title, 'description' => null]), as: 'prof.martin');
        }

        $this->assertSame(['Réussir à 100% en maths'], array_column($this->request('GET', '/api/quizzes?search=100%25', as: 'bob'), 'title'));
        $this->assertSame(['snake_case en Python'], array_column($this->request('GET', '/api/quizzes?search=e_c', as: 'bob'), 'title'));
    }

    public function testQuizImagesMustComeFromTheUploadEndpoint(): void
    {
        $this->request('POST', '/api/quizzes', $this->quizPayload([
            'coverImage' => 'https://pisteur.example/pixel.png',
        ]), as: $this->admin());

        $this->assertResponseStatusCodeSame(422, 'Une image hébergée ailleurs ferait fuiter l’adresse IP des joueurs.');
    }

    public function testUploadedImagesAndTimeLimitsAreSavedWithTheQuiz(): void
    {
        $this->account('prof.martin', 'prof');
        $path = $this->upload('prof.martin');

        $quiz = $this->request('POST', '/api/quizzes', $this->quizPayload([
            'coverImage' => $path,
            'questions' => [['text' => 'Quel est ce pixel ?', 'choices' => ['Rouge', 'Transparent'], 'correctIndex' => 1, 'image' => $path, 'timeLimit' => 45]],
        ]), as: 'prof.martin');

        $this->assertResponseStatusCodeSame(201);
        $this->assertSame($path, $quiz['coverImage']);
        $this->assertSame($path, $quiz['questions'][0]['image']);
        $this->assertSame(45, $quiz['questions'][0]['timeLimit']);
    }

    public function testUploadIsReservedToTeachersAndOnlyAcceptsImages(): void
    {
        $this->request('POST', '/api/uploads', as: 'bob', files: ['image' => $this->pixel()]);
        $this->assertResponseStatusCodeSame(403);

        $this->account('prof.martin', 'prof');
        $text = tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents($text, 'pas une image');
        $this->request('POST', '/api/uploads', as: 'prof.martin', files: ['image' => new UploadedFile($text, 'faux.png', 'image/png', null, true)]);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testPlayingHidesAnswersThenGradesServerSide(): void
    {
        $quizId = $this->createQuiz();

        $questions = $this->request('GET', '/api/quizzes/'.$quizId, as: 'bob')['questions'];
        $this->assertArrayNotHasKey('correctIndex', $questions[0], 'Les bonnes réponses ne doivent pas fuiter en mode jeu.');

        $session = $this->request('POST', '/api/quizzes/'.$quizId.'/sessions', [
            'answers' => [$questions[0]['id'] => 1, $questions[1]['id'] => 1], // 1 bonne, 1 mauvaise
            'durationSeconds' => 12,
        ], as: 'bob');

        $this->assertResponseStatusCodeSame(201);
        $this->assertSame(1, $session['score']);
        $this->assertSame(2, $session['total']);
        $this->assertSame(50, $session['accuracy']);
        $this->assertTrue($session['answers'][0]['correct']);
        $this->assertFalse($session['answers'][1]['correct']);
    }

    public function testHistoryIsScopedToTheAccountButFullForAdmin(): void
    {
        $quizId = $this->createQuiz();
        $mine = $this->request('POST', '/api/quizzes/'.$quizId.'/sessions', ['answers' => []], as: 'bob');
        $this->request('POST', '/api/quizzes/'.$quizId.'/sessions', ['answers' => []], as: 'chloe');

        $this->assertCount(1, $this->request('GET', '/api/sessions', as: 'bob'));
        $this->assertCount(2, $this->request('GET', '/api/sessions?all=1', as: $this->admin()));

        // Le détail d'une partie n'est lisible que par son joueur.
        $this->request('GET', '/api/sessions/'.$mine['id'], as: 'chloe');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testThePlayerSummaryCoversEveryGameBeyondTheHistoryLimit(): void
    {
        $quizId = $this->createQuiz();
        $questions = $this->request('GET', '/api/quizzes/'.$quizId, as: 'bob')['questions'];

        $this->assertSame(['sessionCount' => 0, 'averageAccuracy' => null], $this->request('GET', '/api/sessions/summary', as: 'bob'));

        // 55 parties (au-delà des 50 de l'historique) : 54 à 50 %, une à 100 %.
        foreach (range(1, 55) as $game) {
            $answers = [$questions[0]['id'] => 1, $questions[1]['id'] => 55 === $game ? 0 : 1];
            static::getContainer()->get('cache.rate_limiter')->clear();
            $this->request('POST', '/api/quizzes/'.$quizId.'/sessions', ['answers' => $answers], as: 'bob');
        }
        $this->request('POST', '/api/quizzes/'.$quizId.'/sessions', ['answers' => []], as: 'chloe');

        $this->assertCount(50, $this->request('GET', '/api/sessions', as: 'bob'));
        $this->assertSame(['sessionCount' => 55, 'averageAccuracy' => 51], $this->request('GET', '/api/sessions/summary', as: 'bob'));
    }

    public function testUpdateIsRefusedToPlayers(): void
    {
        $quizId = $this->createQuiz();

        $this->request('PUT', '/api/quizzes/'.$quizId, $this->quizPayload(['title' => 'Titre volé']), as: 'axel');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminUpdateReplacesQuestions(): void
    {
        $quizId = $this->createQuiz();

        $quiz = $this->request('PUT', '/api/quizzes/'.$quizId, [
            'title' => 'Titre corrigé',
            'difficulty' => 'difficile',
            'questions' => [['text' => 'Nouvelle question ?', 'choices' => ['oui', 'non'], 'correctIndex' => 0]],
        ], as: $this->admin());

        $this->assertResponseIsSuccessful();
        $this->assertSame('Titre corrigé', $quiz['title']);
        $this->assertCount(1, $quiz['questions']);
    }

    public function testTeacherOnlyEditsOwnQuizWhileAdminEditsAll(): void
    {
        $quizId = $this->createQuiz('prof.martin');
        $this->account('prof.dupont', 'prof');

        // Un autre professeur ne touche pas au quiz d'un collègue.
        $this->request('DELETE', '/api/quizzes/'.$quizId, as: 'prof.dupont');
        $this->assertResponseStatusCodeSame(403);

        $this->request('DELETE', '/api/quizzes/'.$quizId, as: $this->admin());
        $this->assertResponseStatusCodeSame(204);
    }

    public function testDeletingAQuizKeepsItsGamesInHistory(): void
    {
        $quizId = $this->createQuiz();
        $session = $this->request('POST', '/api/quizzes/'.$quizId.'/sessions', ['answers' => []], as: 'bob');

        $this->request('DELETE', '/api/quizzes/'.$quizId, as: 'prof.martin');
        $this->assertResponseStatusCodeSame(204);

        $history = $this->request('GET', '/api/sessions/'.$session['id'], as: 'bob');
        $this->assertResponseIsSuccessful();
        $this->assertSame('Quiz de test', $history['quizTitle']);
        $this->assertNull($history['quizId']);
    }

    public function testQuizRankingListsEveryParticipant(): void
    {
        $quizId = $this->createQuiz();
        $questions = $this->request('GET', '/api/quizzes/'.$quizId, as: 'bob')['questions'];

        // bob répond juste aux deux questions, chloe se trompe partout.
        $this->request('POST', '/api/quizzes/'.$quizId.'/sessions', [
            'answers' => [$questions[0]['id'] => 1, $questions[1]['id'] => 0],
        ], as: 'bob');
        $this->request('POST', '/api/quizzes/'.$quizId.'/sessions', [
            'answers' => [$questions[0]['id'] => 0, $questions[1]['id'] => 1],
        ], as: 'chloe');

        $ranking = $this->request('GET', '/api/quizzes/'.$quizId.'/sessions', as: 'chloe');

        $this->assertResponseIsSuccessful();
        $this->assertCount(2, $ranking);
        $this->assertSame('bob', $ranking[0]['player'], 'Le meilleur score doit arriver en tête.');
        $this->assertSame(100, $ranking[0]['accuracy']);
        $this->assertSame('chloe', $ranking[1]['player']);
    }

    public function testRankingKeepsOnlyEachPlayersFirstAttempt(): void
    {
        $quizId = $this->createQuiz();
        $questions = $this->request('GET', '/api/quizzes/'.$quizId, as: 'bob')['questions'];

        // bob se trompe partout, lit les bonnes réponses dans la correction, puis rejoue parfaitement en 1 s.
        $first = $this->request('POST', '/api/quizzes/'.$quizId.'/sessions', [
            'answers' => [$questions[0]['id'] => 0, $questions[1]['id'] => 1],
            'durationSeconds' => 30,
        ], as: 'bob');
        $this->request('POST', '/api/quizzes/'.$quizId.'/sessions', [
            'answers' => array_combine(array_column($questions, 'id'), array_column($first['answers'], 'correctIndex')),
            'durationSeconds' => 1,
        ], as: 'bob');
        $this->request('POST', '/api/quizzes/'.$quizId.'/sessions', [
            'answers' => [$questions[0]['id'] => 1, $questions[1]['id'] => 1],
            'durationSeconds' => 20,
        ], as: 'chloe');

        $ranking = $this->request('GET', '/api/quizzes/'.$quizId.'/sessions', as: 'chloe');

        $this->assertSame(['chloe', 'bob'], array_column($ranking, 'player'), 'La partie rejouée ne doit pas compter.');
        $this->assertSame([50, 0], array_column($ranking, 'accuracy'));
        // La partie rejouée reste dans l'historique de bob.
        $this->assertCount(2, $this->request('GET', '/api/sessions', as: 'bob'));
    }

    public function testASessionCannotClaimAnAbsurdDuration(): void
    {
        $quizId = $this->createQuiz();

        foreach ([0, -5, 1_000_000] as $duration) {
            $this->request('POST', '/api/quizzes/'.$quizId.'/sessions', ['answers' => [], 'durationSeconds' => $duration], as: 'bob');
            $this->assertResponseStatusCodeSame(422, sprintf('Durée %d acceptée.', $duration));
        }
    }

    public function testRecordingSessionsIsRateLimited(): void
    {
        $quizId = $this->createQuiz();

        for ($i = 0; $i < 30; ++$i) {
            $this->request('POST', '/api/quizzes/'.$quizId.'/sessions', ['answers' => []], as: 'bob');
            $this->assertResponseStatusCodeSame(201);
        }

        $this->request('POST', '/api/quizzes/'.$quizId.'/sessions', ['answers' => []], as: 'bob');
        $this->assertResponseStatusCodeSame(429);
    }

    public function testAccessKeysAreManagedByAdminsOnly(): void
    {
        $this->account('prof.martin', 'prof');
        $this->request('GET', '/api/access-keys', as: 'prof.martin');
        $this->assertResponseStatusCodeSame(403);

        $key = $this->request('POST', '/api/access-keys', ['role' => 'prof', 'label' => 'Mme Martin'], as: $this->admin());
        $this->assertResponseStatusCodeSame(201);
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{4}(-[A-HJ-NP-Z2-9]{4}){3}$/', $key['value']);
        $this->assertTrue($key['active']);
    }

    public function testAccessKeyRejectsUnknownRole(): void
    {
        $this->request('POST', '/api/access-keys', ['role' => 'pirate'], as: $this->admin());

        $this->assertResponseStatusCodeSame(422);
    }

    public function testUserDirectoryIsAdminOnlyAndOmitsEmails(): void
    {
        $this->account('bob');

        $this->request('GET', '/api/users', as: 'bob');
        $this->assertResponseStatusCodeSame(403);

        $users = $this->request('GET', '/api/users', as: $this->admin());
        $this->assertResponseIsSuccessful();
        $this->assertContains('bob', array_column($users, 'name'));
        $this->assertArrayNotHasKey('email', $users[0]);
    }

    public function testAdminCanWithdrawATeacherRole(): void
    {
        $this->account('prof.martin', 'prof');
        $martin = $this->request('GET', '/api/me', as: 'prof.martin');

        $this->request('PUT', '/api/users/'.$martin['id'].'/role', ['role' => 'user'], as: $this->admin());
        $this->assertResponseIsSuccessful();

        // Le rôle est relu en base à chaque requête : l'effet est immédiat, sans reconnexion.
        $this->request('POST', '/api/quizzes', $this->quizPayload(), as: 'prof.martin');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminCannotDemoteThemself(): void
    {
        $me = $this->request('GET', '/api/me', as: $this->admin());

        $this->request('PUT', '/api/users/'.$me['id'].'/role', ['role' => 'user'], as: $this->admin());

        $this->assertResponseStatusCodeSame(409);
    }

    private function countQueries(string $uri, string $as): int
    {
        $queries = static::getContainer()->get('doctrine.debug_data_holder');
        $queries->reset();
        $this->request('GET', $uri, as: $as);
        $this->assertResponseIsSuccessful();

        return count($queries->getData()['default'] ?? []);
    }

    private function upload(string $as): string
    {
        $payload = $this->request('POST', '/api/uploads', as: $as, files: ['image' => $this->pixel()]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertMatchesRegularExpression('#^/uploads/[a-f0-9]{32}\.png$#', $payload['path']);

        return $payload['path'];
    }

    private function pixel(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($path, base64_decode(self::PIXEL_PNG));

        return new UploadedFile($path, 'pixel.png', 'image/png', null, true);
    }
}
