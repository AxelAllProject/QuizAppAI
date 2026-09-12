<?php

namespace App\Tests;

use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiKeyApiTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testOnlyAdminsManageAiKeys(): void
    {
        $this->account('prof.martin', 'prof');

        $this->request('GET', '/api/ai-keys', as: 'prof.martin');
        $this->assertResponseStatusCodeSame(403);

        $this->request('POST', '/api/ai-keys', ['totalGenerations' => 5], as: 'prof.martin');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAnAdminCreatesAKeyWithADefaultOfFiveGenerations(): void
    {
        $key = $this->request('POST', '/api/ai-keys', [], as: $this->admin());

        $this->assertResponseStatusCodeSame(201);
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{4}(-[A-HJ-NP-Z2-9]{4}){3}$/', $key['value']);
        $this->assertSame(5, $key['totalGenerations']);
        $this->assertSame(5, $key['remainingGenerations']);
        $this->assertTrue($key['active']);
        $this->assertNull($key['redeemedBy']);
    }

    public function testRedeemingAKeyUnlocksGenerationAndIsReflectedOnMe(): void
    {
        $this->account('prof.martin', 'prof');
        $key = $this->request('POST', '/api/ai-keys', ['totalGenerations' => 5, 'label' => 'Mme Martin'], as: $this->admin());

        $me = $this->request('GET', '/api/me', as: 'prof.martin');
        $this->assertNull($me['aiKey'], 'Sans clé redeem, aucun accès à la génération.');

        $me = $this->request('POST', '/api/me/ai-key', ['key' => $key['value']], as: 'prof.martin');
        $this->assertResponseIsSuccessful();
        $this->assertSame(5, $me['aiKey']['remainingGenerations']);
        $this->assertSame(5, $me['aiKey']['totalGenerations']);
    }

    public function testAssigningAKeyDirectlyToAProfSkipsTheRedemptionStep(): void
    {
        $this->account('prof.martin', 'prof');

        $key = $this->request('POST', '/api/ai-keys', [
            'totalGenerations' => 3,
            'userId' => $this->request('GET', '/api/me', as: 'prof.martin')['id'],
        ], as: $this->admin());

        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('prof.martin', $key['redeemedBy']);

        // Débloqué tout de suite, sans que prof.martin saisisse la clé lui-même.
        $me = $this->request('GET', '/api/me', as: 'prof.martin');
        $this->assertSame(3, $me['aiKey']['remainingGenerations']);
    }

    public function testAssigningToAnUnknownUserFails(): void
    {
        $this->request('POST', '/api/ai-keys', ['userId' => 999999], as: $this->admin());

        $this->assertResponseStatusCodeSame(404);
    }

    public function testEachGenerationConsumesOneCreditAndStopsAtZero(): void
    {
        $this->account('prof.martin', 'prof');
        $this->giveAiKey('prof.martin', totalGenerations: 2);
        $this->mockGroqResponse();

        $first = $this->request('POST', '/api/ai/quizzes', ['topic' => 'Sujet 1'], as: 'prof.martin');
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame(1, $first['aiGenerationsLeft']);
        $this->assertSame(1, $this->request('GET', '/api/me', as: 'prof.martin')['aiKey']['remainingGenerations']);

        $second = $this->request('POST', '/api/ai/quizzes', ['topic' => 'Sujet 2'], as: 'prof.martin');
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame(0, $second['aiGenerationsLeft']);
        $this->assertNull($this->request('GET', '/api/me', as: 'prof.martin')['aiKey'], 'Clé épuisée : elle ne doit plus apparaître comme active.');

        $this->request('POST', '/api/ai/quizzes', ['topic' => 'Sujet 3'], as: 'prof.martin');
        $this->assertResponseStatusCodeSame(402, 'Plus aucune génération disponible.');
    }

    public function testAFailedGenerationDoesNotConsumeTheCredit(): void
    {
        $this->account('prof.martin', 'prof');
        $this->giveAiKey('prof.martin', totalGenerations: 1);
        $this->mockGroqResponse(['title' => 'Sans questions', 'questions' => []]);

        $this->request('POST', '/api/ai/quizzes', ['topic' => 'Rien'], as: 'prof.martin');
        $this->assertResponseStatusCodeSame(502);

        $this->assertSame(1, $this->request('GET', '/api/me', as: 'prof.martin')['aiKey']['remainingGenerations'], 'Un brouillon invalide ne doit pas coûter de crédit.');
    }

    public function testAKeyCanOnlyBeRedeemedByOnePerson(): void
    {
        $this->account('prof.martin', 'prof');
        $this->account('prof.dupont', 'prof');
        $key = $this->request('POST', '/api/ai-keys', [], as: $this->admin());

        $this->request('POST', '/api/me/ai-key', ['key' => $key['value']], as: 'prof.martin');
        $this->assertResponseIsSuccessful();

        $this->request('POST', '/api/me/ai-key', ['key' => $key['value']], as: 'prof.dupont');
        $this->assertResponseStatusCodeSame(409);

        // Ressaisir sa propre clé déjà liée ne doit pas échouer (idempotent).
        $this->request('POST', '/api/me/ai-key', ['key' => $key['value']], as: 'prof.martin');
        $this->assertResponseIsSuccessful();
    }

    public function testARevokedKeyCanNeitherBeRedeemedNorUsed(): void
    {
        $this->account('prof.martin', 'prof');
        $this->giveAiKey('prof.martin');
        $this->mockGroqResponse();
        $this->request('POST', '/api/ai/quizzes', ['topic' => 'Avant révocation'], as: 'prof.martin');
        $this->assertResponseStatusCodeSame(201);

        $keys = $this->request('GET', '/api/ai-keys', as: $this->admin());
        $this->request('DELETE', '/api/ai-keys/'.$keys[0]['id'], as: $this->admin());
        $this->assertResponseIsSuccessful();

        $this->request('POST', '/api/ai/quizzes', ['topic' => 'Après révocation'], as: 'prof.martin');
        $this->assertResponseStatusCodeSame(402, 'Une clé révoquée ne doit plus donner accès à la génération.');

        $this->account('prof.dupont', 'prof');
        $this->request('POST', '/api/me/ai-key', ['key' => $keys[0]['value']], as: 'prof.dupont');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAnExpiredKeyStopsWorkingOnceItsDateHasPassed(): void
    {
        $clock = static::mockTime();
        $this->account('prof.martin', 'prof');
        $key = $this->request('POST', '/api/ai-keys', ['expiresInDays' => 1], as: $this->admin());
        $this->request('POST', '/api/me/ai-key', ['key' => $key['value']], as: 'prof.martin');
        $this->mockGroqResponse();

        $this->request('POST', '/api/ai/quizzes', ['topic' => 'Avant expiration'], as: 'prof.martin');
        $this->assertResponseStatusCodeSame(201);

        $clock->sleep(2 * 24 * 3600);

        $this->request('POST', '/api/ai/quizzes', ['topic' => 'Après expiration'], as: 'prof.martin');
        $this->assertResponseStatusCodeSame(402);
    }

    public function testAdminsAlsoNeedAnAiKeyToGenerate(): void
    {
        // Le rôle admin ne suffit pas non plus : la clé IA reste un débloquage à part entière.
        $this->request('POST', '/api/ai/quizzes', ['topic' => 'Le système solaire'], as: $this->admin());

        $this->assertResponseStatusCodeSame(402);
    }

    public function testDeletingTheAccountFreesTheKeyWithoutRestoringItsCredits(): void
    {
        $this->account('prof.martin', 'prof');
        $key = $this->request('POST', '/api/ai-keys', ['totalGenerations' => 3], as: $this->admin());
        $this->request('POST', '/api/me/ai-key', ['key' => $key['value']], as: 'prof.martin');
        $this->mockGroqResponse();
        $this->request('POST', '/api/ai/quizzes', ['topic' => 'Sujet'], as: 'prof.martin');

        $this->request('DELETE', '/api/me', ['password' => self::PASSWORD], as: 'prof.martin');
        $this->assertResponseStatusCodeSame(204);

        $keys = $this->request('GET', '/api/ai-keys', as: $this->admin());
        $this->assertSame('compte supprimé', $keys[0]['redeemedBy'], 'Le pseudo réel ne doit plus être affiché, comme pour un quiz ou une clé d’accès.');
        $this->assertSame(2, $keys[0]['remainingGenerations'], 'Les crédits déjà consommés restent perdus.');

        // Le solde restant redevient disponible pour qui saisit le code.
        $this->account('prof.dupont', 'prof');
        $this->request('POST', '/api/me/ai-key', ['key' => $key['value']], as: 'prof.dupont');
        $this->assertResponseIsSuccessful();
    }

    private function mockGroqResponse(?array $quiz = null): void
    {
        $quiz ??= ['title' => 'Quiz de test', 'questions' => [['text' => 'Q ?', 'choices' => ['a', 'b'], 'correctIndex' => 0]]];
        $body = json_encode([
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => json_encode($quiz)], 'finish_reason' => 'stop'],
            ],
        ]);
        static::getContainer()->set(HttpClientInterface::class, new MockHttpClient(
            static fn () => new MockResponse($body, ['response_headers' => ['content-type' => 'application/json']]),
        ));
    }
}
