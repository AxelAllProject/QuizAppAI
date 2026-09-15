<?php

namespace App\Tests;

/**
 * Filtres du back-office : l'annuaire des comptes et le listing des clés IA se
 * réduisent côté serveur, pour que l'écran reste tenable quand la base grossit.
 */
class AdminDirectoryApiTest extends ApiTestCase
{
    public function testTheAccountDirectoryIsFilteredByRoleAndName(): void
    {
        $this->account('bob');
        $this->account('brigitte');
        $this->account('prof.martin', 'prof');

        $this->assertSame(
            ['prof.martin'],
            $this->names($this->request('GET', '/api/users?role=prof', as: $this->admin())),
        );

        $this->assertSame(
            ['brigitte', 'bob'],
            $this->names($this->request('GET', '/api/users?role=user', as: $this->admin())),
        );

        $this->assertSame(
            ['brigitte', 'bob'],
            $this->names($this->request('GET', '/api/users?search=B', as: $this->admin())),
            'La recherche doit ignorer la casse.',
        );

        $this->assertSame(
            ['bob'],
            $this->names($this->request('GET', '/api/users?search=bob&role=user', as: $this->admin())),
            'Les deux filtres se combinent.',
        );
    }

    public function testAnUnknownRoleFilterIsRejectedRatherThanIgnored(): void
    {
        $this->request('GET', '/api/users?role=roi', as: $this->admin());

        $this->assertResponseStatusCodeSame(422);
    }

    public function testTheDirectoryStaysReservedToAdminsEvenWithFilters(): void
    {
        $this->account('prof.martin', 'prof');

        $this->request('GET', '/api/users?role=admin', as: 'prof.martin');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAiKeysAreFilteredByStatusAndFreeTextSearch(): void
    {
        $this->account('prof.martin', 'prof');

        $free = $this->request('POST', '/api/ai-keys', ['label' => 'Réserve'], as: $this->admin());
        $held = $this->request('POST', '/api/ai-keys', [
            'label' => 'Mme Martin',
            'userId' => $this->request('GET', '/api/me', as: 'prof.martin')['id'],
        ], as: $this->admin());
        $dead = $this->request('POST', '/api/ai-keys', ['label' => 'À jeter'], as: $this->admin());
        $this->request('DELETE', '/api/ai-keys/'.$dead['id'], as: $this->admin());

        $this->assertSame(
            [$free['id']],
            $this->ids($this->request('GET', '/api/ai-keys?status=unclaimed', as: $this->admin())),
        );
        $this->assertSame(
            [$dead['id']],
            $this->ids($this->request('GET', '/api/ai-keys?status=revoked', as: $this->admin())),
        );
        $this->assertSame(
            [$held['id'], $free['id']],
            $this->ids($this->request('GET', '/api/ai-keys?status=active', as: $this->admin())),
        );
        $this->assertSame(
            [$held['id']],
            $this->ids($this->request('GET', '/api/ai-keys?search=martin', as: $this->admin())),
            'La recherche couvre le détenteur autant que l’étiquette.',
        );
    }

    public function testAnExhaustedAiKeyIsListedAsSuchRatherThanActive(): void
    {
        $this->account('prof.martin', 'prof');
        $key = $this->request('POST', '/api/ai-keys', [
            'totalGenerations' => 1,
            'userId' => $this->request('GET', '/api/me', as: 'prof.martin')['id'],
        ], as: $this->admin());

        $this->assertSame('active', $key['status']);

        $this->request('DELETE', '/api/ai-keys/'.$key['id'], as: $this->admin());
        $this->assertSame('revoked', $this->json()['status']);
    }

    /** @param array<array<string, mixed>> $rows */
    private function ids(array $rows): array
    {
        return array_map(static fn (array $row) => $row['id'], $rows);
    }

    /** @param array<array<string, mixed>> $rows */
    private function names(array $rows): array
    {
        return array_map(static fn (array $row) => $row['name'], $rows);
    }
}
