<?php

namespace App\Tests;

/**
 * Attribution directe d'une clé d'accès à un compte choisi par l'admin (par opposition
 * au code partagé, saisi par la personne elle-même — déjà couvert par QuizApiTest).
 */
class AccessKeyApiTest extends ApiTestCase
{
    public function testAssigningAKeyToAnExistingUserGrantsTheRoleImmediately(): void
    {
        $this->account('bob');

        $key = $this->request('POST', '/api/access-keys', [
            'role' => 'prof',
            'label' => 'Attribution directe',
            'userId' => $this->userId('bob'),
        ], as: $this->admin());

        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('bob', $key['assignedTo']);
        $this->assertFalse($key['active'], 'La clé a rempli son rôle : elle ne doit plus être partageable.');

        // Le rôle est effectif tout de suite, sans que bob saisisse quoi que ce soit.
        $this->request('POST', '/api/quizzes', $this->quizPayload(), as: 'bob');
        $this->assertResponseStatusCodeSame(201);
    }

    public function testADirectlyAssignedKeyCannotAlsoBeRedeemedAsACode(): void
    {
        $this->account('bob');
        $key = $this->request('POST', '/api/access-keys', [
            'role' => 'prof',
            'userId' => $this->userId('bob'),
        ], as: $this->admin());

        $this->request('POST', '/api/register', [
            'email' => 'chloe@exemple.test',
            'name' => 'chloe',
            'password' => self::PASSWORD,
            'consent' => true,
            'accessKey' => $key['value'],
        ]);

        $this->assertResponseStatusCodeSame(422, 'Une clé attribuée directement ne doit plus être un code valide.');
    }

    public function testAssigningToAnUnknownUserFails(): void
    {
        $this->request('POST', '/api/access-keys', ['role' => 'prof', 'userId' => 999999], as: $this->admin());

        $this->assertResponseStatusCodeSame(404);
    }

    public function testAssigningPromotesButNeverDemotes(): void
    {
        $this->account('chef', 'admin');

        // Donner « prof » à un admin ne doit pas le rétrograder.
        $this->request('POST', '/api/access-keys', ['role' => 'prof', 'userId' => $this->userId('chef')], as: $this->admin());

        $this->assertResponseStatusCodeSame(201);
        $accounts = $this->request('GET', '/api/users', as: $this->admin());
        $chef = current(array_filter($accounts, static fn ($a) => 'chef' === $a['name']));
        $this->assertSame('admin', $chef['role']);
    }

    public function testDeletingTheAssignedAccountAnonymizesTheHistoryEntry(): void
    {
        $this->account('bob');
        $key = $this->request('POST', '/api/access-keys', ['role' => 'prof', 'userId' => $this->userId('bob')], as: $this->admin());

        $this->request('DELETE', '/api/me', ['password' => self::PASSWORD], as: 'bob');
        $this->assertResponseStatusCodeSame(204);

        $keys = $this->request('GET', '/api/access-keys', as: $this->admin());
        $found = current(array_filter($keys, static fn ($k) => $k['id'] === $key['id']));
        $this->assertSame('compte supprimé', $found['assignedTo']);
    }

    private function userId(string $name): int
    {
        return $this->request('GET', '/api/me', as: $name)['id'];
    }
}
