<?php

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Base des tests d'API : base remise à zéro, comptes créés par l'inscription elle-même. */
abstract class ApiTestCase extends WebTestCase
{
    protected const PASSWORD = 'motdepasse';

    protected KernelBrowser $client;

    /** @var array<string, string> jeton de connexion, par pseudo */
    private array $tokens = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Sans ça, le noyau (et donc le conteneur) reboote à chaque requête : un service
        // remplacé par un test (ex. le client HTTP de l'IA) ne survivrait pas à la requête suivante.
        $this->client->disableReboot();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        // Le quota de génération IA vit sur disque (pas en base) : il doit repartir à zéro
        // à chaque test, sinon un compte « prof.martin » hérite du quota déjà entamé par un autre test.
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    /**
     * Inscrit le compte au premier appel et renvoie son jeton. Le rôle passe par
     * une vraie clé d'accès : clé de secours pour le premier admin (« direction »), puis clé
     * générée par lui pour les autres — la clé de secours ne sert plus une fois un admin inscrit.
     *
     * Chaque compte s'inscrit depuis sa propre adresse IP : un test qui fait jouer plus de cinq
     * comptes ne doit pas buter sur la limite d'inscriptions par IP.
     */
    protected function account(string $name, string $role = 'user'): string
    {
        if (isset($this->tokens[$name])) {
            return $this->tokens[$name];
        }

        $key = match ($role) {
            'admin' => 'direction' === $name ? 'admin' : $this->request('POST', '/api/access-keys', ['role' => 'admin'], as: $this->admin())['value'],
            'prof' => $this->request('POST', '/api/access-keys', ['role' => 'prof'], as: $this->admin())['value'],
            default => null,
        };

        $payload = $this->request('POST', '/api/register', [
            'email' => $name.'@exemple.test',
            'name' => $name,
            'password' => self::PASSWORD,
            'consent' => true,
            'accessKey' => $key,
        ], server: ['REMOTE_ADDR' => '10.0.0.'.(count($this->tokens) + 1)]);
        $this->assertResponseStatusCodeSame(201, 'Inscription impossible pour '.$name);

        return $this->tokens[$name] = $payload['token'];
    }

    protected function admin(): string
    {
        $this->account('direction', 'admin');

        return 'direction';
    }

    /**
     * @param string|null $as pseudo du compte qui fait la requête (inscrit comme joueur s'il n'existe pas)
     */
    protected function request(string $method, string $uri, ?array $payload = null, ?string $as = null, array $files = [], array $server = []): mixed
    {
        $server += $files ? [] : ['CONTENT_TYPE' => 'application/json'];

        if (null !== $as) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$this->account($as);
        }

        $this->client->request($method, $uri, files: $files, server: $server, content: null === $payload ? null : json_encode($payload));

        return $this->json();
    }

    protected function json(): mixed
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    protected function quizPayload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Quiz de test',
            'description' => 'Pour les tests',
            'category' => 'Divers',
            'difficulty' => 'facile',
            'questions' => [
                ['text' => 'Combien font 2 + 2 ?', 'choices' => ['3', '4'], 'correctIndex' => 1],
                ['text' => 'Capitale de la France ?', 'choices' => ['Paris', 'Lyon'], 'correctIndex' => 0],
            ],
        ];
    }

    protected function createQuiz(string $author = 'prof.martin'): int
    {
        $this->account($author, 'prof');
        $quiz = $this->request('POST', '/api/quizzes', $this->quizPayload(), as: $author);
        $this->assertResponseStatusCodeSame(201);

        return $quiz['id'];
    }

    /** Crée une clé IA (admin) et la lie tout de suite au compte donné : débloque la génération pour les tests. */
    protected function giveAiKey(string $to, int $totalGenerations = 5): void
    {
        $key = $this->request('POST', '/api/ai-keys', ['totalGenerations' => $totalGenerations], as: $this->admin());
        $this->assertResponseStatusCodeSame(201, 'Impossible de créer la clé IA de test.');

        $this->request('POST', '/api/me/ai-key', ['key' => $key['value']], as: $to);
        $this->assertResponseIsSuccessful('Impossible de lier la clé IA à '.$to);
    }
}
