<?php

namespace App\Tests;

use App\Identity\Domain\Model\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

class AccountApiTest extends ApiTestCase
{
    public function testLoginIsRateLimitedAgainstBruteForcing(): void
    {
        $this->request('POST', '/api/register', $this->registration());

        for ($i = 0; $i < 10; ++$i) {
            $this->request('POST', '/api/login', ['email' => 'axel@exemple.test', 'password' => 'mauvais-mot']);
            $this->assertResponseStatusCodeSame(401);
        }

        // Même avec le bon mot de passe : la limite se déclenche avant que la requête soit traitée.
        $this->request('POST', '/api/login', ['email' => 'axel@exemple.test', 'password' => self::PASSWORD]);

        $this->assertResponseStatusCodeSame(429);
        $this->assertNotEmpty($this->client->getResponse()->headers->get('Retry-After'));
    }

    /** Un attaquant qui change d'adresse IP à chaque essai reste limité sur le compte visé. */
    public function testLoginFailuresAreAlsoLimitedPerAccountWhateverTheIp(): void
    {
        $this->request('POST', '/api/register', $this->registration());

        for ($i = 0; $i < 10; ++$i) {
            $this->request('POST', '/api/login', ['email' => 'axel@exemple.test', 'password' => 'mauvais-mot'], server: ['REMOTE_ADDR' => "203.0.113.$i"]);
            $this->assertResponseStatusCodeSame(401);
        }

        $this->request('POST', '/api/login', ['email' => 'axel@exemple.test', 'password' => self::PASSWORD], server: ['REMOTE_ADDR' => '203.0.113.99']);

        $this->assertResponseStatusCodeSame(429);
        $this->assertNotEmpty($this->client->getResponse()->headers->get('Retry-After'));
    }

    /** Les échecs anciens ne pénalisent pas le vrai propriétaire une fois qu'il s'est connecté. */
    public function testASuccessfulLoginClearsTheAccountFailures(): void
    {
        $this->request('POST', '/api/register', $this->registration());

        foreach ([9, 9] as $round => $failures) {
            for ($i = 0; $i < $failures; ++$i) {
                $this->request('POST', '/api/login', ['email' => 'axel@exemple.test', 'password' => 'mauvais-mot'], server: ['REMOTE_ADDR' => "198.51.$round.$i"]);
                $this->assertResponseStatusCodeSame(401);
            }

            $this->request('POST', '/api/login', ['email' => 'axel@exemple.test', 'password' => self::PASSWORD], server: ['REMOTE_ADDR' => "198.51.$round.200"]);
            $this->assertResponseIsSuccessful();
        }
    }

    public function testRegistrationIsRateLimitedAgainstMassAccountCreation(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->request('POST', '/api/register', $this->registration(['email' => "compte$i@exemple.test", 'name' => "compte$i"]));
            $this->assertResponseStatusCodeSame(201);
        }

        $this->request('POST', '/api/register', $this->registration(['email' => 'compte-en-trop@exemple.test', 'name' => 'compte-en-trop']));

        $this->assertResponseStatusCodeSame(429);
    }

    public function testRegistrationReturnsATokenAndThePlayerRole(): void
    {
        $payload = $this->request('POST', '/api/register', $this->registration());

        $this->assertResponseStatusCodeSame(201);
        $this->assertNotEmpty($payload['token']);
        $this->assertSame('axel', $payload['user']['name']);
        $this->assertSame('user', $payload['user']['role']);
        $this->assertNotNull($payload['user']['consentedAt']);
    }

    public function testRegistrationRequiresConsent(): void
    {
        $this->request('POST', '/api/register', $this->registration(['consent' => false]));

        $this->assertResponseStatusCodeSame(422);
    }

    public function testRegistrationRejectsShortPasswordAndInvalidEmail(): void
    {
        $payload = $this->request('POST', '/api/register', $this->registration(['email' => 'pas-un-email', 'password' => 'court']));

        $this->assertResponseStatusCodeSame(422);
        $this->assertCount(2, $payload['errors']);
    }

    public function testEmailAndNameAreUniqueRegardlessOfCase(): void
    {
        $this->request('POST', '/api/register', $this->registration());

        $payload = $this->request('POST', '/api/register', $this->registration(['email' => 'AXEL@exemple.test', 'name' => 'Axel']));

        $this->assertResponseStatusCodeSame(422);
        $this->assertCount(2, $payload['errors']);
    }

    public function testNamesDifferingOnlyByAccentedCaseAreTheSamePseudo(): void
    {
        $this->request('POST', '/api/register', $this->registration(['email' => 'zoe@exemple.test', 'name' => 'Élodie']));
        $this->assertResponseStatusCodeSame(201);

        $this->request('POST', '/api/register', $this->registration(['email' => 'autre@exemple.test', 'name' => 'éLODIE']));

        $this->assertResponseStatusCodeSame(422, 'LOWER() de SQLite ne traite pas « É » : la comparaison doit se faire sur la forme canonique.');
    }

    public function testTheDatabaseRefusesTwoPseudosDifferingOnlyByCase(): void
    {
        // Simule deux inscriptions simultanées : la vérification en PHP est contournée, seule la base protège.
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist((new User())->setEmail('a@exemple.test')->setUsername('Zoé')->setPassword('x'));
        $em->persist((new User())->setEmail('b@exemple.test')->setUsername('ZOÉ')->setPassword('x'));

        $this->expectException(UniqueConstraintViolationException::class);
        $em->flush();
    }

    public function testLoginChecksThePassword(): void
    {
        $this->request('POST', '/api/register', $this->registration());

        $this->request('POST', '/api/login', ['email' => 'axel@exemple.test', 'password' => 'mauvais-mot']);
        $this->assertResponseStatusCodeSame(401);

        $payload = $this->request('POST', '/api/login', ['email' => 'Axel@Exemple.test', 'password' => self::PASSWORD]);
        $this->assertResponseIsSuccessful();
        $this->assertSame('axel', $payload['user']['name']);
    }

    public function testTheApiRequiresAValidToken(): void
    {
        $this->request('GET', '/api/quizzes');
        $this->assertResponseStatusCodeSame(401);

        $this->client->request('GET', '/api/quizzes', server: ['HTTP_AUTHORIZATION' => 'Bearer jeton-invente']);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testRolesCannotBeForgedFromTheRequest(): void
    {
        $token = $this->account('bob');

        // L'ancien en-tête X-Role n'a plus aucun effet.
        $this->client->request('GET', '/api/access-keys', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_X_ROLE' => 'admin']);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testLogoutRevokesTheToken(): void
    {
        $token = $this->account('bob');

        $this->client->request('POST', '/api/logout', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        $this->assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/me', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testAGeneratedKeyGrantsItsRoleThenStopsOnceRevoked(): void
    {
        $key = $this->request('POST', '/api/access-keys', ['role' => 'prof'], as: $this->admin());

        $payload = $this->request('POST', '/api/register', $this->registration(['name' => 'martin', 'email' => 'martin@exemple.test', 'accessKey' => $key['value']]));
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('prof', $payload['user']['role']);

        $this->request('DELETE', '/api/access-keys/'.$key['id'], as: $this->admin());
        $this->assertResponseIsSuccessful();

        $this->request('POST', '/api/register', $this->registration(['accessKey' => $key['value']]));
        $this->assertResponseStatusCodeSame(422);

        $this->request('POST', '/api/me/access-key', ['key' => $key['value']], as: 'bob');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAnAccessKeyPromotesButNeverDemotes(): void
    {
        $this->request('POST', '/api/me/access-key', ['key' => 'admin'], as: 'bob');
        $this->assertResponseIsSuccessful();
        $this->assertSame('admin', $this->request('GET', '/api/me', as: 'bob')['role']);

        $key = $this->request('POST', '/api/access-keys', ['role' => 'prof'], as: 'bob');
        $me = $this->request('POST', '/api/me/access-key', ['key' => $key['value']], as: 'bob');

        $this->assertSame('admin', $me['role']);
    }

    /** Un code admin qui circule ne doit faire administrateur que la première personne qui le saisit. */
    public function testAnAdminKeyWorksOnlyOnce(): void
    {
        $key = $this->request('POST', '/api/access-keys', ['role' => 'admin', 'label' => 'Nouvelle direction'], as: $this->admin());

        $payload = $this->request('POST', '/api/register', $this->registration(['accessKey' => $key['value']]));
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('admin', $payload['user']['role']);

        $this->request('POST', '/api/me/access-key', ['key' => $key['value']], as: 'bob');
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame('user', $this->request('GET', '/api/me', as: 'bob')['role']);

        $listed = current(array_filter($this->request('GET', '/api/access-keys', as: $this->admin()), static fn ($k) => $k['id'] === $key['id']));
        $this->assertSame('assigned', $listed['status']);
        $this->assertSame('axel', $listed['assignedTo']);
    }

    /** Une clé prof, elle, se partage : toute l'équipe pédagogique peut saisir la même. */
    public function testATeacherKeyCanBeSharedByATeam(): void
    {
        $key = $this->request('POST', '/api/access-keys', ['role' => 'prof', 'label' => 'Équipe'], as: $this->admin());

        foreach (['bob', 'chloe'] as $name) {
            $this->request('POST', '/api/me/access-key', ['key' => $key['value']], as: $name);
            $this->assertResponseIsSuccessful();
            $this->assertSame('prof', $this->request('GET', '/api/me', as: $name)['role']);
        }
    }

    public function testTheBootstrapKeyStopsWorkingOnceAnAdminExists(): void
    {
        $this->admin();

        $this->request('POST', '/api/me/access-key', ['key' => 'admin'], as: 'bob');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testExportContainsEverythingKnownAboutTheAccount(): void
    {
        $quizId = $this->createQuiz();
        $this->request('POST', '/api/quizzes/'.$quizId.'/sessions', ['answers' => []], as: 'bob');

        $this->request('GET', '/api/me/export', as: 'bob');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('attachment', (string) $this->client->getResponse()->headers->get('Content-Disposition'));

        $export = $this->json();
        $this->assertSame('bob@exemple.test', $export['account']['email']);
        $this->assertCount(1, $export['sessions']);
        $this->assertSame([], $export['quizzes']);
        $this->assertCount(1, $this->request('GET', '/api/me/export', as: 'prof.martin')['quizzes']);
    }

    public function testDeletingTheAccountErasesPersonalDataAndAnonymizesQuizzes(): void
    {
        $quizId = $this->createQuiz('prof.martin');
        $this->request('POST', '/api/quizzes/'.$quizId.'/sessions', ['answers' => []], as: 'prof.martin');
        $token = $this->account('prof.martin');

        $this->request('DELETE', '/api/me', ['password' => 'pas-le-bon'], as: 'prof.martin');
        $this->assertResponseStatusCodeSame(403);

        $this->request('DELETE', '/api/me', ['password' => self::PASSWORD], as: 'prof.martin');
        $this->assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/me', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        $this->assertResponseStatusCodeSame(401);

        $quiz = $this->request('GET', '/api/quizzes/'.$quizId, as: 'bob');
        $this->assertSame('compte supprimé', $quiz['author'], 'Le quiz reste jouable, sans le pseudo de son auteur.');
        $this->assertSame([], $this->request('GET', '/api/sessions?all=1', as: $this->admin()));
        $this->assertNotContains('prof.martin', array_column($this->request('GET', '/api/users', as: $this->admin()), 'name'));

        // L'adresse est libérée : la personne peut revenir plus tard.
        $this->request('POST', '/api/register', $this->registration(['email' => 'prof.martin@exemple.test', 'name' => 'prof.martin']));
        $this->assertResponseStatusCodeSame(201);
    }

    public function testDeletionPasswordCannotBeGuessedWithAStolenToken(): void
    {
        $this->account('bob');

        for ($i = 0; $i < 5; ++$i) {
            $this->request('DELETE', '/api/me', ['password' => 'essai-'.$i], as: 'bob');
            $this->assertResponseStatusCodeSame(403);
        }

        // Même le bon mot de passe est refusé tant que la limite court : sinon l'essai suivant révélerait s'il est juste.
        $this->request('DELETE', '/api/me', ['password' => self::PASSWORD], as: 'bob');
        $this->assertResponseStatusCodeSame(429);
        $this->assertResponseHasHeader('Retry-After');
        $this->request('GET', '/api/me', as: 'bob');
        $this->assertResponseIsSuccessful('Le compte ne doit pas avoir été supprimé.');
    }

    public function testDeletionFailuresOfOneAccountDoNotBlockAnother(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->request('DELETE', '/api/me', ['password' => 'essai-'.$i], as: 'bob');
        }

        $this->request('DELETE', '/api/me', ['password' => self::PASSWORD], as: 'chloe');
        $this->assertResponseStatusCodeSame(204);
    }

    private function registration(array $overrides = []): array
    {
        return $overrides + [
            'email' => 'axel@exemple.test',
            'name' => 'axel',
            'password' => self::PASSWORD,
            'consent' => true,
        ];
    }
}
