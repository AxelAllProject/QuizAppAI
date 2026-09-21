<?php

namespace App\Tests;

use Symfony\Component\Mime\Email;

/** « Mot de passe oublié » : demander le lien, puis s'en servir une fois. */
class PasswordResetApiTest extends ApiTestCase
{
    public function testAResetLinkIsEmailedAndLetsTheAccountChooseANewPassword(): void
    {
        $this->account('axel');

        $this->request('POST', '/api/password-reset', ['email' => 'axel@exemple.test']);
        $this->assertResponseIsSuccessful();
        $this->assertEmailCount(1);

        $this->request('POST', '/api/password-reset/confirm', ['token' => $this->tokenFromEmail(), 'password' => 'nouveau-mot-de-passe']);
        $this->assertResponseIsSuccessful();

        $this->request('POST', '/api/login', ['email' => 'axel@exemple.test', 'password' => 'nouveau-mot-de-passe']);
        $this->assertResponseIsSuccessful();

        $this->request('POST', '/api/login', ['email' => 'axel@exemple.test', 'password' => self::PASSWORD]);
        $this->assertResponseStatusCodeSame(401);
    }

    /** Un lien qui traîne dans une boîte partagée ne doit pas rester une porte ouverte. */
    public function testAResetLinkOnlyWorksOnce(): void
    {
        $this->account('axel');
        $this->request('POST', '/api/password-reset', ['email' => 'axel@exemple.test']);
        $token = $this->tokenFromEmail();

        $this->request('POST', '/api/password-reset/confirm', ['token' => $token, 'password' => 'premier-mot-de-passe']);
        $this->assertResponseIsSuccessful();

        $this->request('POST', '/api/password-reset/confirm', ['token' => $token, 'password' => 'second-mot-de-passe']);
        $this->assertResponseStatusCodeSame(400);

        $this->request('POST', '/api/login', ['email' => 'axel@exemple.test', 'password' => 'second-mot-de-passe']);
        $this->assertResponseStatusCodeSame(401);
    }

    /** Redemander un lien invalide le précédent : sinon deux liens vivraient en parallèle. */
    public function testAskingAgainInvalidatesThePreviousLink(): void
    {
        $this->account('axel');

        $this->request('POST', '/api/password-reset', ['email' => 'axel@exemple.test']);
        $first = $this->tokenFromEmail();

        $this->request('POST', '/api/password-reset', ['email' => 'axel@exemple.test']);
        $second = $this->tokenFromEmail();

        $this->assertNotSame($first, $second);

        $this->request('POST', '/api/password-reset/confirm', ['token' => $first, 'password' => 'nouveau-mot-de-passe']);
        $this->assertResponseStatusCodeSame(400);
    }

    /**
     * Le formulaire ne doit pas servir d'annuaire : la réponse est la même que l'adresse
     * ait un compte ou non. Seule différence, invisible du dehors : aucun e-mail ne part.
     */
    public function testAnUnknownEmailGetsTheSameAnswerAndNoEmail(): void
    {
        $this->account('axel');

        $this->request('POST', '/api/password-reset', ['email' => 'axel@exemple.test']);
        $known = $this->json();
        $this->assertEmailCount(1);

        $this->request('POST', '/api/password-reset', ['email' => 'personne@exemple.test'], server: ['REMOTE_ADDR' => '203.0.113.7']);

        $this->assertResponseIsSuccessful();
        $this->assertSame($known, $this->json());
        // Les compteurs d'e-mails portent sur la dernière requête : celle-ci n'en a envoyé aucun.
        $this->assertEmailCount(0);
    }

    /** Changer de mot de passe ferme les sessions déjà ouvertes sur le compte. */
    public function testResettingRevokesTheExistingTokens(): void
    {
        $token = $this->account('axel');
        $this->request('GET', '/api/me', as: 'axel');
        $this->assertResponseIsSuccessful();

        $this->request('POST', '/api/password-reset', ['email' => 'axel@exemple.test']);
        $this->request('POST', '/api/password-reset/confirm', ['token' => $this->tokenFromEmail(), 'password' => 'nouveau-mot-de-passe']);

        $this->client->request('GET', '/api/me', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testAnUnknownTokenIsRefused(): void
    {
        $this->request('POST', '/api/password-reset/confirm', ['token' => str_repeat('a', 64), 'password' => 'nouveau-mot-de-passe']);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testATooShortPasswordIsRefused(): void
    {
        $this->account('axel');
        $this->request('POST', '/api/password-reset', ['email' => 'axel@exemple.test']);

        $this->request('POST', '/api/password-reset/confirm', ['token' => $this->tokenFromEmail(), 'password' => 'court']);

        $this->assertResponseStatusCodeSame(422);
    }

    /** Sans limite, la route servirait à inonder la boîte de quelqu'un. */
    public function testRequestsAreRateLimitedPerIp(): void
    {
        $this->account('axel');

        for ($i = 0; $i < 5; ++$i) {
            $this->request('POST', '/api/password-reset', ['email' => 'axel@exemple.test']);
            $this->assertResponseIsSuccessful();
            $this->assertEmailCount(1);
        }

        $this->request('POST', '/api/password-reset', ['email' => 'axel@exemple.test']);

        $this->assertResponseStatusCodeSame(429);
        // Refusée avant traitement : la sixième demande ne déclenche aucun envoi.
        $this->assertEmailCount(0);
    }

    /** Un jeton de 64 caractères ne se devine pas, mais la route reste ouverte sans jeton. */
    public function testConfirmationsAreRateLimitedPerIp(): void
    {
        for ($i = 0; $i < 10; ++$i) {
            $this->request('POST', '/api/password-reset/confirm', ['token' => str_repeat((string) $i, 64), 'password' => 'nouveau-mot-de-passe']);
            $this->assertResponseStatusCodeSame(400);
        }

        $this->request('POST', '/api/password-reset/confirm', ['token' => str_repeat('f', 64), 'password' => 'nouveau-mot-de-passe']);

        $this->assertResponseStatusCodeSame(429);
    }

    /** Récupère le jeton dans le lien contenu dans l'e-mail envoyé. */
    private function tokenFromEmail(int $index = 0): string
    {
        $email = $this->getMailerMessage($index);
        $this->assertInstanceOf(Email::class, $email);
        $this->assertMatchesRegularExpression('#/mot-de-passe/[0-9a-f]{64}#', $email->getTextBody());

        preg_match('#/mot-de-passe/([0-9a-f]{64})#', $email->getTextBody(), $matches);

        return $matches[1];
    }
}
