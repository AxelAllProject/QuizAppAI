<?php

namespace App\Tests;

use App\Access\Infrastructure\DataFixtures\AccessKeyFixtures;
use App\Ai\Infrastructure\DataFixtures\AiKeyFixtures;
use App\Identity\Infrastructure\DataFixtures\UserFixtures;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Les fixtures chargées, le site doit être utilisable tel quel : on s'y connecte,
 * on y retrouve la bibliothèque, les parties et les clés documentées.
 */
class DataFixturesTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $loader = static::getContainer()->get('doctrine.fixtures.loader');
        (new ORMExecutor($em, new ORMPurger($em)))->execute($loader->getFixtures());
        $em->clear();
    }

    public function testEveryDemoAccountCanLogInWithItsRole(): void
    {
        foreach (UserFixtures::ACCOUNTS as $username => $role) {
            $payload = $this->login($username);

            $this->assertSame($role, $payload['user']['role'], $username);
        }
    }

    public function testLibraryAndHistoriesArePopulated(): void
    {
        $token = $this->login('lea')['token'];

        $this->assertCount(4, $this->authenticated('GET', '/api/quizzes', $token));

        // 3 parties solo + la partie en direct terminée.
        $this->assertCount(4, $this->authenticated('GET', '/api/sessions', $token));

        $stats = $this->authenticated('GET', '/api/stats', $token);
        $this->assertSame(14, $stats['sessionCount']);
        $this->assertNotEmpty($stats['leaderboard']);
    }

    public function testDocumentedTeacherKeyGrantsTheTeacherRole(): void
    {
        $payload = $this->request('POST', '/api/register', [
            'email' => 'nouveau.prof@quizlab.test',
            'name' => 'nouveau.prof',
            'password' => UserFixtures::PASSWORD,
            'consent' => true,
            'accessKey' => AccessKeyFixtures::TEACHER_KEY,
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('prof', $payload['user']['role']);
    }

    public function testFreeAiKeyCanBeRedeemed(): void
    {
        $token = $this->login('m.durand')['token'];

        $this->authenticated('POST', '/api/me/ai-key', $token, ['key' => AiKeyFixtures::FREE_KEY]);

        $this->assertResponseIsSuccessful();
    }

    private function login(string $username): array
    {
        $payload = $this->request('POST', '/api/login', ['email' => UserFixtures::email($username), 'password' => UserFixtures::PASSWORD]);
        $this->assertResponseIsSuccessful('Connexion impossible pour '.$username);

        return $payload;
    }

    private function authenticated(string $method, string $uri, string $token, ?array $payload = null): mixed
    {
        $this->client->request($method, $uri, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token], content: null === $payload ? null : json_encode($payload));

        return $this->json();
    }
}
