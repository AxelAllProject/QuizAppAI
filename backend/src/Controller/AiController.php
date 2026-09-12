<?php

namespace App\Controller;

use App\Dto\GenerateQuizInput;
use App\Dto\QuestionInput;
use App\Dto\QuizInput;
use App\Exception\AiGenerationException;
use App\Repository\AiKeyRepository;
use App\Service\AiQuizGenerator;
use App\Service\Identity;
use App\Service\QuizNormalizer;
use App\Service\QuizWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Rédaction de quiz par IA : le professeur décrit le sujet, l'IA rédige les questions
 * et le quiz est publié directement — comme s'il avait rempli l'éditeur lui-même.
 * La même validation que l'éditeur manuel (QuizInput) s'applique avant d'écrire quoi que
 * ce soit : un brouillon mal formé est refusé plutôt que publié à moitié.
 *
 * Fonctionnalité premium : avoir le rôle professeur ou administrateur ne suffit pas,
 * il faut en plus détenir une clé IA active (voir AiKey) — le rôle ouvre la porte,
 * la clé donne le nombre de générations.
 */
#[Route('/api/ai')]
#[IsGranted('ROLE_TEACHER', message: 'Il faut être professeur ou administrateur pour créer un quiz par IA.')]
class AiController extends AbstractController
{
    public function __construct(
        private readonly AiQuizGenerator $generator,
        private readonly QuizWriter $writer,
        private readonly QuizNormalizer $normalizer,
        private readonly ValidatorInterface $validator,
        private readonly AiKeyRepository $aiKeys,
        private readonly EntityManagerInterface $em,
        #[Autowire(service: 'limiter.ai_quiz_generation')]
        private readonly RateLimiterFactory $aiQuizGenerationLimiter,
        private readonly Identity $identity,
    ) {
    }

    #[Route('/quizzes', name: 'api_ai_quiz_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] GenerateQuizInput $input): JsonResponse
    {
        $user = $this->identity->user();
        $aiKey = $this->aiKeys->findActiveFor($user);

        if (!$aiKey) {
            return $this->json(['error' => 'Fonctionnalité premium : demande une clé IA à un administrateur, puis saisis-la dans « Mon compte ».'], 402);
        }

        $limit = $this->aiQuizGenerationLimiter->create((string) $user->getId())->consume();

        if (!$limit->isAccepted()) {
            $wait = max(1, $limit->getRetryAfter()->getTimestamp() - time());

            return $this->json(['error' => sprintf('Trop de générations : réessaie dans %d minute(s).', (int) ceil($wait / 60))], 429);
        }

        try {
            $draft = $this->generator->generate($input);
            $quizInput = $this->toQuizInput($draft);

            if (count($this->validator->validate($quizInput)) > 0) {
                throw new AiGenerationException('L’IA a renvoyé un quiz inexploitable : réessaie, ou reformule le sujet.', 502);
            }

            $quiz = $this->writer->create($quizInput, $user, $this->identity->name());
            $aiKey->consume();
            $this->em->flush();

            return $this->json($this->normalizer->detail($quiz, true) + ['canEdit' => true, 'aiGenerationsLeft' => $aiKey->getRemainingGenerations()], 201);
        } catch (AiGenerationException $exception) {
            return $this->json(['error' => $exception->getMessage()], $exception->getStatusCode());
        }
    }

    /** @param array{title: string, description: ?string, category: string, difficulty: string, questions: list<array{text: string, choices: list<string>, correctIndex: int, explanation: ?string}>} $draft */
    private function toQuizInput(array $draft): QuizInput
    {
        return new QuizInput(
            title: $draft['title'],
            description: $draft['description'],
            category: $draft['category'],
            difficulty: $draft['difficulty'],
            questions: array_map(
                static fn (array $q) => new QuestionInput(text: $q['text'], choices: $q['choices'], correctIndex: $q['correctIndex'], explanation: $q['explanation']),
                $draft['questions'],
            ),
        );
    }
}
