<?php

namespace App\Tests;

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
