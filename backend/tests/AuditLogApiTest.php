<?php

namespace App\Tests;

use Monolog\Level;

/** Journal d'audit : les actions sensibles sont tracées, sans donnée secrète. */
class AuditLogApiTest extends ApiTestCase
{
    public function testWritesAreLoggedWithTheAccountAndTheRoute(): void
    {
        $this->account('prof.martin', 'prof');
        $logs = $this->captureLogs('audit');

        $quiz = $this->request('POST', '/api/quizzes', $this->quizPayload(), as: 'prof.martin');
        $this->request('DELETE', '/api/quizzes/'.$quiz['id'], as: 'prof.martin');

        $records = $logs->getRecords();
        $this->assertCount(2, $records);
        $this->assertSame('api_quiz_create', $records[0]->context['route']);
        $this->assertSame(201, $records[0]->context['status']);
        $this->assertSame(Level::Info, $records[0]->level);
        $this->assertEquals(['id' => $quiz['id']], $records[1]->context['route_params']);
        $this->assertNotNull($records[1]->context['user_id']);
    }

    public function testReadsAreNotLoggedButRefusalsAre(): void
    {
        $quizId = $this->createQuiz();
        $this->account('bob');
        $logs = $this->captureLogs('audit');

        $this->request('GET', '/api/quizzes/'.$quizId, as: 'bob');
        $this->assertCount(0, $logs->getRecords(), 'Une simple lecture ne doit pas remplir le journal.');

        $this->request('PUT', '/api/quizzes/'.$quizId, $this->quizPayload(['title' => 'Titre volé']), as: 'bob');
        $this->request('GET', '/api/users', as: 'bob');

        $this->assertResponseStatusCodeSame(403);
        $this->assertTrue($logs->hasWarningThatContains('api_quiz_update'));
        $this->assertTrue($logs->hasWarningThatContains('api_user_list'), 'Un refus d’accès en lecture reste une tentative à tracer.');
    }

    public function testPasswordsAndTokensNeverReachTheLog(): void
    {
        $this->account('bob');
        $logs = $this->captureLogs('audit');

        $login = $this->request('POST', '/api/login', ['email' => 'bob@exemple.test', 'password' => self::PASSWORD]);
        $this->request('POST', '/api/login', ['email' => 'bob@exemple.test', 'password' => 'mauvais-mot-de-passe']);

        $this->assertCount(2, $logs->getRecords());
        $this->assertTrue($logs->hasWarningThatContains('api_login → 401'));
        $dump = json_encode(array_map(static fn ($record) => [$record->message, $record->context], $logs->getRecords()));
        $this->assertStringNotContainsString(self::PASSWORD, $dump);
        $this->assertStringNotContainsString('mauvais-mot-de-passe', $dump);
        $this->assertStringNotContainsString($login['token'], $dump);
        $this->assertStringNotContainsString('bob@exemple.test', $dump);
    }
}
