<?php

namespace App\Tests;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiQuizGenerationApiTest extends ApiTestCase
{
    public function testOnlyTeachersCanGenerateAQuiz(): void
    {
        $this->request('POST', '/api/ai/quizzes', ['topic' => 'Le système solaire'], as: 'bob');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testHavingTheTeacherRoleIsNotEnoughWithoutAnAiKey(): void
    {
        $this->account('prof.martin', 'prof');

        $this->request('POST', '/api/ai/quizzes', ['topic' => 'Le système solaire'], as: 'prof.martin');

        $this->assertResponseStatusCodeSame(402, 'La génération est une fonctionnalité premium : le rôle seul ne suffit pas.');
    }

    public function testAGeneratedQuizIsSanitizedAndPublishedImmediately(): void
    {
        $this->account('prof.martin', 'prof');
        $this->giveAiKey('prof.martin');
        $this->mockGroqResponse([
            'title' => '  Le système solaire  ',
            'description' => 'Un tour des planètes.',
            'category' => 'Sciences',
            'difficulty' => 'facile',
            'questions' => [
                [
                    'text' => 'Quelle planète est la plus proche du Soleil ?',
                    'choices' => ['Vénus', 'Mercure', 'Mars', 'Terre'],
                    'correctIndex' => 1,
                    'explanation' => 'Mercure est la première planète.',
                ],
                // Question invalide (correctIndex hors bornes) : doit être filtrée, pas bloquer le reste.
                [
                    'text' => 'Question cassée',
                    'choices' => ['a', 'b'],
                    'correctIndex' => 9,
                ],
            ],
        ]);

        $quiz = $this->request('POST', '/api/ai/quizzes', [
            'topic' => 'Le système solaire',
            'questionCount' => 2,
            'choiceCount' => 4,
            'difficulty' => 'facile',
        ], as: 'prof.martin');

        $this->assertResponseStatusCodeSame(201, 'Le quiz doit être publié, pas seulement proposé en brouillon.');
        $this->assertSame('Le système solaire', $quiz['title'], 'Le titre doit être nettoyé (espaces).');
        $this->assertSame('prof.martin', $quiz['author']);
        $this->assertTrue($quiz['canEdit']);
        $this->assertCount(1, $quiz['questions'], 'La question invalide doit être écartée.');
        $this->assertSame(1, $quiz['questions'][0]['correctIndex']);

        // Le quiz existe vraiment : il apparaît dans la bibliothèque et se joue.
        $library = $this->request('GET', '/api/quizzes', as: 'bob');
        $this->assertCount(1, $library);
        $this->assertSame($quiz['id'], $library[0]['id']);

        $played = $this->request('GET', '/api/quizzes/'.$quiz['id'], as: 'bob');
        $this->assertArrayNotHasKey('correctIndex', $played['questions'][0], 'Les bonnes réponses ne fuitent pas en mode jeu.');
    }

    public function testUnusableAiResponseIsReportedWithoutPublishingAnything(): void
    {
        $this->account('prof.martin', 'prof');
        $this->giveAiKey('prof.martin');
        $this->mockGroqResponse(['title' => 'Sans questions', 'questions' => []]);

        $this->request('POST', '/api/ai/quizzes', ['topic' => 'Rien'], as: 'prof.martin');

        $this->assertResponseStatusCodeSame(502);
        $this->assertSame([], $this->request('GET', '/api/quizzes', as: 'prof.martin'));
    }

    public function testATitleTooShortForPublicationIsCaughtEvenIfTheGeneratorAcceptedIt(): void
    {
        $this->account('prof.martin', 'prof');
        $this->giveAiKey('prof.martin');
        // « Ok » passe le nettoyage interne du générateur (non vide) mais pas la validation
        // de publication (3 caractères minimum) : le filet de sécurité doit s'en apercevoir.
        $this->mockGroqResponse([
            'title' => 'Ok',
            'questions' => [['text' => 'Q ?', 'choices' => ['a', 'b'], 'correctIndex' => 0]],
        ]);

        $this->request('POST', '/api/ai/quizzes', ['topic' => 'Rien'], as: 'prof.martin');

        $this->assertResponseStatusCodeSame(502);
        $this->assertSame([], $this->request('GET', '/api/quizzes', as: 'prof.martin'));
    }

    public function testQuotaExhaustionIsReportedAsUnavailable(): void
    {
        $this->account('prof.martin', 'prof');
        $this->giveAiKey('prof.martin');
        // Statut 429 : la réponse ne devient une ClientException qu'à la lecture (toArray), comme en vrai.
        static::getContainer()->set(HttpClientInterface::class, new \Symfony\Component\HttpClient\MockHttpClient(
            static fn () => new MockResponse('{"error":{"message":"quota"}}', ['http_code' => 429]),
        ));

        $this->request('POST', '/api/ai/quizzes', ['topic' => 'Le système solaire'], as: 'prof.martin');

        $this->assertResponseStatusCodeSame(503);
    }

    public function testGenerationIsRateLimitedToProtectTheFreeQuota(): void
    {
        $this->account('prof.martin', 'prof');
        $this->giveAiKey('prof.martin', totalGenerations: 20);
        $this->mockGroqResponse([
            'title' => 'Quiz',
            'questions' => [['text' => 'Q ?', 'choices' => ['a', 'b'], 'correctIndex' => 0]],
        ]);

        for ($i = 0; $i < 15; ++$i) {
            $this->request('POST', '/api/ai/quizzes', ['topic' => 'Sujet '.$i], as: 'prof.martin');
            $this->assertResponseIsSuccessful();
        }

        $this->request('POST', '/api/ai/quizzes', ['topic' => 'Un de trop'], as: 'prof.martin');

        $this->assertResponseStatusCodeSame(429);
    }

    /** Reproduit la forme réelle d'une réponse Groq (endpoint chat/completions, compatible OpenAI). */
    private function mockGroqResponse(array $quiz): void
    {
        $body = json_encode([
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => json_encode($quiz)], 'finish_reason' => 'stop'],
            ],
        ]);
        static::getContainer()->set(HttpClientInterface::class, new \Symfony\Component\HttpClient\MockHttpClient(
            static fn () => new MockResponse($body, ['response_headers' => ['content-type' => 'application/json']]),
        ));
    }
}
