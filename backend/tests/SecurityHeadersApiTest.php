<?php

namespace App\Tests;

/** En-têtes de sécurité posés sur les réponses de l'API. */
class SecurityHeadersApiTest extends ApiTestCase
{
    public function testApiResponsesCarryTheSecurityHeaders(): void
    {
        $this->request('GET', '/api/quizzes', as: 'bob');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        $this->assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
        $this->assertResponseHeaderSame('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
        $this->assertResponseHeaderSame('X-Frame-Options', 'DENY');
    }

    public function testErrorResponsesAreProtectedToo(): void
    {
        $this->request('GET', '/api/quizzes');

        $this->assertResponseStatusCodeSame(401);
        $this->assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        $this->assertResponseHeaderSame('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
    }

    public function testHstsIsOnlySentOverHttps(): void
    {
        $this->request('GET', '/api/quizzes', as: 'bob');
        $this->assertResponseNotHasHeader('Strict-Transport-Security', 'En HTTP, l’en-tête serait ignoré et trompeur.');

        $this->request('GET', '/api/quizzes', as: 'bob', server: ['HTTPS' => 'on']);
        $this->assertResponseHeaderSame('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function testCorsPreflightStillWorksForTheFrontend(): void
    {
        $this->client->request('OPTIONS', '/api/quizzes', server: [
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $this->assertResponseHeaderSame('Access-Control-Allow-Origin', 'http://localhost:5173');
    }
}
