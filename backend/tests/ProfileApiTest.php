<?php

namespace App\Tests;

/** « Mon compte » : changer son e-mail, son pseudo ou son mot de passe. */
class ProfileApiTest extends ApiTestCase
{
    public function testTheAccountCanChangeItsEmailAndUsername(): void
    {
        $payload = $this->request('PUT', '/api/me', [
            'password' => self::PASSWORD,
            'email' => 'nouvelle@exemple.test',
            'name' => 'axel.b',
        ], as: 'axel');

        $this->assertResponseIsSuccessful();
        $this->assertSame('nouvelle@exemple.test', $payload['email']);
        $this->assertSame('axel.b', $payload['name']);

        // L'adresse de connexion suit : c'est la nouvelle qui ouvre une session, plus l'ancienne.
        $this->request('POST', '/api/login', ['email' => 'nouvelle@exemple.test', 'password' => self::PASSWORD]);
        $this->assertResponseIsSuccessful();

        $this->request('POST', '/api/login', ['email' => 'axel@exemple.test', 'password' => self::PASSWORD]);
        $this->assertResponseStatusCodeSame(401);
    }

    /** Le jeton seul ne suffit pas : sans le mot de passe, on ne détourne pas l'adresse du compte. */
    public function testChangingTheProfileRequiresTheCurrentPassword(): void
    {
        $this->request('PUT', '/api/me', ['password' => 'mauvais-mot', 'email' => 'pirate@exemple.test'], as: 'axel');

        $this->assertResponseStatusCodeSame(403);
        $this->assertSame('axel@exemple.test', $this->request('GET', '/api/me', as: 'axel')['email']);
    }

    public function testANewPasswordReplacesTheOldOne(): void
    {
        $this->request('PUT', '/api/me', ['password' => self::PASSWORD, 'newPassword' => 'nouveau-mot-de-passe'], as: 'axel');
        $this->assertResponseIsSuccessful();

        $this->request('POST', '/api/login', ['email' => 'axel@exemple.test', 'password' => 'nouveau-mot-de-passe']);
        $this->assertResponseIsSuccessful();

        $this->request('POST', '/api/login', ['email' => 'axel@exemple.test', 'password' => self::PASSWORD]);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testAnEmailAlreadyTakenIsRefused(): void
    {
        $this->account('lea');

        $this->request('PUT', '/api/me', ['password' => self::PASSWORD, 'email' => 'lea@exemple.test'], as: 'axel');

        $this->assertResponseStatusCodeSame(409);
        $this->assertNotEmpty($this->json()['errors']);
    }

    /** « Axel » et « axel » ne peuvent pas coexister, ici comme à l'inscription. */
    public function testAUsernameAlreadyTakenIsRefusedWhateverTheCase(): void
    {
        $this->account('lea');

        $this->request('PUT', '/api/me', ['password' => self::PASSWORD, 'name' => 'LEA'], as: 'axel');

        $this->assertResponseStatusCodeSame(409);
    }

    /** Renvoyer ses propres valeurs ne doit pas se heurter à « déjà pris » par soi-même. */
    public function testKeepingItsOwnEmailAndUsernameIsAccepted(): void
    {
        $this->request('PUT', '/api/me', [
            'password' => self::PASSWORD,
            'email' => 'axel@exemple.test',
            'name' => 'axel',
        ], as: 'axel');

        $this->assertResponseIsSuccessful();
    }

    /** Corriger la casse de son propre pseudo reste possible : la forme normalisée ne change pas. */
    public function testTheAccountCanFixTheCaseOfItsOwnUsername(): void
    {
        $payload = $this->request('PUT', '/api/me', ['password' => self::PASSWORD, 'name' => 'Axel'], as: 'axel');

        $this->assertResponseIsSuccessful();
        $this->assertSame('Axel', $payload['name']);
    }

    public function testAnEmptyFormChangesNothing(): void
    {
        $this->request('PUT', '/api/me', ['password' => self::PASSWORD], as: 'axel');

        $this->assertResponseStatusCodeSame(400);
    }

    /** Avec un jeton volé, le mot de passe ne doit pas se deviner par essais successifs. */
    public function testWrongPasswordsAreRateLimited(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->request('PUT', '/api/me', ['password' => 'mauvais-mot', 'name' => 'pirate'], as: 'axel');
            $this->assertResponseStatusCodeSame(403);
        }

        // Même avec le bon mot de passe : la limite se déclenche avant la vérification.
        $this->request('PUT', '/api/me', ['password' => self::PASSWORD, 'name' => 'axel.b'], as: 'axel');

        $this->assertResponseStatusCodeSame(429);
        $this->assertNotEmpty($this->client->getResponse()->headers->get('Retry-After'));
    }

    public function testAnInvalidEmailIsRefused(): void
    {
        $this->request('PUT', '/api/me', ['password' => self::PASSWORD, 'email' => 'pas-une-adresse'], as: 'axel');

        $this->assertResponseStatusCodeSame(422);
    }

    public function testTheRouteRequiresAToken(): void
    {
        $this->request('PUT', '/api/me', ['password' => self::PASSWORD, 'name' => 'anonyme']);

        $this->assertResponseStatusCodeSame(401);
    }
}
