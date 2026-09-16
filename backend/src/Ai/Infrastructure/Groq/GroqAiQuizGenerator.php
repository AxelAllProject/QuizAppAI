<?php

namespace App\Ai\Infrastructure\Groq;

use App\Ai\Application\AiQuizGenerator;
use App\Ai\Application\Dto\GenerateQuizInput;
use App\Ai\Domain\Exception\AiGenerationException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Rédige un brouillon de quiz avec l'API Groq (gratuite, compatible OpenAI, quotée
 * en débit mais pas en volume). Cette classe ne fait que rédiger et nettoyer le
 * brouillon — c'est AiController qui le valide (mêmes règles qu'un quiz saisi à la
 * main) et le publie via QuizWriter.
 */
#[AsAlias(AiQuizGenerator::class)]
class GroqAiQuizGenerator implements AiQuizGenerator
{
    private const ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';

    /** L'inférence Groq est très rapide, mais on laisse une marge avant d'abandonner. */
    private const TIMEOUT_SECONDS = 30;

    /**
     * Consigne de ton, de sécurité et de forme : Groq garantit un JSON syntaxiquement
     * valide (response_format json_object) mais pas sa forme exacte — on la décrit ici
     * en toutes lettres, et sanitize() reste le filet de sécurité si le modèle s'en écarte.
     */
    private const SYSTEM_INSTRUCTION = <<<'TEXT'
        Tu rédiges des questions de quiz à choix unique pour un contexte scolaire, en français.
        Les questions doivent être factuelles, vérifiables, adaptées à un public scolaire
        (rien de violent, haineux, sexuel ou discriminatoire), et une seule proposition doit
        être correcte par question. Les propositions doivent être plausibles, de longueur
        comparable, et ne pas se recouper.

        Réponds UNIQUEMENT avec un objet JSON de cette forme exacte, sans texte avant ni après :
        {
          "title": "string",
          "description": "string",
          "category": "un ou deux mots",
          "difficulty": "facile" | "moyen" | "difficile",
          "questions": [
            {
              "text": "string",
              "choices": ["string", "string", ...],
              "correctIndex": 0,
              "explanation": "string"
            }
          ]
        }
        TEXT;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(GROQ_API_KEY)%')]
        private readonly string $apiKey,
        #[Autowire('%env(GROQ_MODEL)%')]
        private readonly string $model,
    ) {
    }

    /** Indique si une clé d'API Groq est configurée. */
    public function isConfigured(): bool
    {
        return '' !== $this->apiKey;
    }

    /** @return array{title: string, description: ?string, category: string, difficulty: string, questions: list<array{text: string, choices: list<string>, correctIndex: int, explanation: ?string}>} */
    public function generate(GenerateQuizInput $input): array
    {
        if (!$this->isConfigured()) {
            throw new AiGenerationException('Génération par IA indisponible : demande à un administrateur de configurer GROQ_API_KEY.', 503);
        }

        try {
            $response = $this->httpClient->request('POST', self::ENDPOINT, [
                'auth_bearer' => $this->apiKey,
                'timeout' => self::TIMEOUT_SECONDS,
                'max_duration' => self::TIMEOUT_SECONDS,
                'json' => [
                    'model' => $this->model,
                    'temperature' => 0.8,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM_INSTRUCTION],
                        ['role' => 'user', 'content' => $this->prompt($input)],
                    ],
                ],
            ]);

            $outputText = $response->toArray()['choices'][0]['message']['content'] ?? null;
        } catch (ClientException $exception) {
            throw 429 === $exception->getResponse()->getStatusCode() ? new AiGenerationException('Quota gratuit de génération épuisé pour l’instant : réessaie dans quelques minutes.', 503) : new AiGenerationException('L’IA n’a pas pu générer de quiz : '.$this->apiErrorMessage($exception), 502);
        } catch (TransportExceptionInterface) {
            throw new AiGenerationException('Impossible de joindre le service de génération par IA.', 503);
        }

        if (!is_string($outputText)) {
            throw new AiGenerationException('Réponse inattendue du service de génération par IA.', 502);
        }

        return $this->sanitize(json_decode($outputText, true) ?? [], $input);
    }

    /** Rédige la consigne envoyée à l'IA à partir de la demande du professeur. */
    private function prompt(GenerateQuizInput $input): string
    {
        return sprintf(
            "Sujet du quiz : %s\nNombre de questions : %d\nNombre de propositions par question : %d\nNiveau de difficulté : %s\n\nRédige un quiz complet sur ce sujet, avec un titre accrocheur, une courte description, une catégorie d'un ou deux mots, et exactement %d question(s) de %d proposition(s) chacune (le champ correctIndex indique l'index, à partir de 0, de la bonne proposition dans le tableau choices). Ajoute une courte explication pédagogique par question.",
            $input->topic,
            $input->questionCount,
            $input->choiceCount,
            $input->difficulty,
            $input->questionCount,
            $input->choiceCount,
        );
    }

    /**
     * Vérifie que la réponse est exploitable avant de la renvoyer au front : la validation
     * complète reste celle de QuizInput au moment où le professeur publie réellement le quiz.
     */
    private function sanitize(mixed $data, GenerateQuizInput $input): array
    {
        $fail = static fn () => throw new AiGenerationException('L’IA a renvoyé un quiz inexploitable : réessaie, ou reformule le sujet.', 502);

        if (!is_array($data) || !is_string($data['title'] ?? null) || '' === trim($data['title'])) {
            $fail();
        }

        $questions = $data['questions'] ?? null;

        if (!is_array($questions) || [] === $questions) {
            $fail();
        }

        $sanitized = [];

        foreach (array_values($questions) as $question) {
            $choices = array_values(array_filter(array_map(
                static fn ($choice) => is_string($choice) ? trim($choice) : null,
                is_array($question['choices'] ?? null) ? $question['choices'] : [],
            ), static fn ($choice) => null !== $choice && '' !== $choice));

            $text = is_string($question['text'] ?? null) ? trim($question['text']) : '';
            $correctIndex = $question['correctIndex'] ?? null;

            if ('' === $text || count($choices) < 2 || !is_int($correctIndex) || $correctIndex < 0 || $correctIndex >= count($choices)) {
                continue;
            }

            $sanitized[] = [
                'text' => $text,
                'choices' => $choices,
                'correctIndex' => $correctIndex,
                'explanation' => is_string($question['explanation'] ?? null) ? (trim($question['explanation']) ?: null) : null,
            ];
        }

        if ([] === $sanitized) {
            $fail();
        }

        return [
            'title' => trim($data['title']),
            'description' => is_string($data['description'] ?? null) ? (trim($data['description']) ?: null) : null,
            'category' => is_string($data['category'] ?? null) && '' !== trim($data['category']) ? trim($data['category']) : 'Général',
            'difficulty' => in_array($data['difficulty'] ?? null, ['facile', 'moyen', 'difficile'], true) ? $data['difficulty'] : $input->difficulty,
            'questions' => $sanitized,
        ];
    }

    /** Extrait le message d'erreur renvoyé par l'API Groq. */
    private function apiErrorMessage(ClientException $exception): string
    {
        $body = $exception->getResponse()->toArray(false);

        return is_string($body['error']['message'] ?? null) ? $body['error']['message'] : 'erreur inconnue.';
    }
}
