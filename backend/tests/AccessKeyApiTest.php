<?php

namespace App\Tests;

use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Attribution directe d'une clé d'accès à un compte choisi par l'admin (par opposition
 * au code partagé, saisi par la personne elle-même — déjà couvert par QuizApiTest).
 */
class AccessKeyApiTest extends ApiTestCase
{
    use ClockSensitiveTrait;

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

    public function testAKeyWithAnExpiryStopsGrantingTheRoleOnceTheDateHasPassed(): void
    {
        $clock = static::mockTime();
        $key = $this->request('POST', '/api/access-keys', ['role' => 'prof', 'expiresInDays' => 1], as: $this->admin());

        $this->assertResponseStatusCodeSame(201);
        $this->assertNotNull($key['expiresAt']);
        $this->assertSame('active', $key['status']);

        $clock->sleep(2 * 24 * 3600);

        $this->request('POST', '/api/register', [
            'email' => 'chloe@exemple.test',
            'name' => 'chloe',
            'password' => self::PASSWORD,
            'consent' => true,
            'accessKey' => $key['value'],
        ]);
        $this->assertResponseStatusCodeSame(422, 'Une clé périmée ne doit plus conférer de rôle.');

        $listed = $this->find($this->request('GET', '/api/access-keys', as: $this->admin()), $key['id']);
        $this->assertSame('expired', $listed['status']);
        $this->assertFalse($listed['active']);
    }

    public function testAKeyWithoutAnExpiryNeverExpires(): void
    {
        $clock = static::mockTime();
        $key = $this->request('POST', '/api/access-keys', ['role' => 'prof'], as: $this->admin());
        $this->assertNull($key['expiresAt']);

        $clock->sleep(400 * 24 * 3600);

        $this->request('POST', '/api/register', [
            'email' => 'chloe@exemple.test',
            'name' => 'chloe',
            'password' => self::PASSWORD,
            'consent' => true,
            'accessKey' => $key['value'],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('prof', $this->json()['user']['role']);
    }

    public function testAnAbsurdExpiryIsRejected(): void
    {
        $this->request('POST', '/api/access-keys', ['role' => 'prof', 'expiresInDays' => 0], as: $this->admin());

        $this->assertResponseStatusCodeSame(422);
    }

    public function testKeysAreFilteredByRoleStatusAndFreeTextSearch(): void
    {
        $clock = static::mockTime();
        $this->account('bob');

        $prof = $this->request('POST', '/api/access-keys', ['role' => 'prof', 'label' => 'Mme Martin'], as: $this->admin());
        $admin = $this->request('POST', '/api/access-keys', ['role' => 'admin', 'label' => 'Direction'], as: $this->admin());
        $short = $this->request('POST', '/api/access-keys', ['role' => 'prof', 'expiresInDays' => 1], as: $this->admin());
        $assigned = $this->request('POST', '/api/access-keys', ['role' => 'prof', 'userId' => $this->userId('bob')], as: $this->admin());
        $this->request('DELETE', '/api/access-keys/'.$admin['id'], as: $this->admin());

        $clock->sleep(2 * 24 * 3600);

        $this->assertSame(
            [$prof['id']],
            $this->ids($this->request('GET', '/api/access-keys?status=active', as: $this->admin())),
        );
        $this->assertSame(
            [$short['id']],
            $this->ids($this->request('GET', '/api/access-keys?status=expired', as: $this->admin())),
        );
        $this->assertSame(
            [$assigned['id']],
            $this->ids($this->request('GET', '/api/access-keys?status=assigned', as: $this->admin())),
        );
        $this->assertSame(
            [$admin['id']],
            $this->ids($this->request('GET', '/api/access-keys?status=revoked', as: $this->admin())),
        );

        // Le rôle exclut la clé admin — qui est aussi la clé de secours de l'administrateur du test.
        $byRole = $this->request('GET', '/api/access-keys?role=admin', as: $this->admin());
        $this->assertSame([$admin['id']], $this->ids($byRole));

        // La recherche libre couvre l'étiquette, le code et le bénéficiaire d'une attribution.
        $this->assertSame([$prof['id']], $this->ids($this->request('GET', '/api/access-keys?search=martin', as: $this->admin())));
        $this->assertSame([$assigned['id']], $this->ids($this->request('GET', '/api/access-keys?search=bob', as: $this->admin())));
        $this->assertSame([$short['id']], $this->ids($this->request('GET', '/api/access-keys?search='.$short['value'], as: $this->admin())));
    }

    public function testAnUnknownStatusFilterIsRejectedRatherThanIgnored(): void
    {
        $this->request('GET', '/api/access-keys?status=n-importe-quoi', as: $this->admin());

        $this->assertResponseStatusCodeSame(422);
    }

    /** @param array<array<string, mixed>> $keys */
    private function ids(array $keys): array
    {
        return array_map(static fn (array $key) => $key['id'], $keys);
    }

    /** @param array<array<string, mixed>> $keys */
    private function find(array $keys, int $id): array
    {
        return current(array_filter($keys, static fn (array $key) => $key['id'] === $id));
    }

    private function userId(string $name): int
    {
        return $this->request('GET', '/api/me', as: $name)['id'];
    }
}
