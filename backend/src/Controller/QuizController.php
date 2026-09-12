<?php

namespace App\Controller;

use App\Dto\QuizInput;
use App\Entity\Quiz;
use App\Repository\GameSessionRepository;
use App\Repository\LiveGameRepository;
use App\Repository\QuizRepository;
use App\Service\Identity;
use App\Service\QuizNormalizer;
use App\Service\QuizWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class QuizController extends AbstractController
{
    public function __construct(
        private readonly QuizRepository $quizzes,
        private readonly GameSessionRepository $sessions,
        private readonly LiveGameRepository $liveGames,
        private readonly EntityManagerInterface $em,
        private readonly Identity $identity,
        private readonly QuizNormalizer $normalizer,
        private readonly QuizWriter $writer,
    ) {
    }

    #[Route('/quizzes', name: 'api_quiz_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $mine = $request->query->getBoolean('mine');
        $found = $this->quizzes->search(
            $request->query->get('search'),
            $request->query->get('category'),
            $mine ? $this->identity->user() : null,
        );

        return $this->json(array_map(
            fn (Quiz $quiz) => $this->normalizer->summary($quiz) + ['canEdit' => $this->identity->canEdit($quiz)],
            $found,
        ));
    }

    #[Route('/categories', name: 'api_categories', methods: ['GET'])]
    public function categories(): JsonResponse
    {
        return $this->json($this->quizzes->findCategories());
    }

    #[Route('/quizzes/{id}', name: 'api_quiz_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, Request $request): JsonResponse
    {
        $quiz = $this->quizzes->find($id);

        if (!$quiz) {
            return $this->json(['error' => 'Quiz introuvable.'], 404);
        }

        $canEdit = $this->identity->canEdit($quiz);
        // Les bonnes réponses ne sortent que pour l'édition, jamais pour jouer.
        $withAnswers = $request->query->getBoolean('withAnswers') && $canEdit;

        return $this->json($this->normalizer->detail($quiz, $withAnswers) + ['canEdit' => $canEdit]);
    }

    #[Route('/quizzes', name: 'api_quiz_create', methods: ['POST'])]
    #[IsGranted('ROLE_TEACHER', message: 'Il faut être professeur ou administrateur pour créer un quiz.')]
    public function create(#[MapRequestPayload] QuizInput $input): JsonResponse
    {
        $quiz = $this->writer->create($input, $this->identity->user(), $this->identity->name());

        return $this->json($this->normalizer->detail($quiz, true) + ['canEdit' => true], 201);
    }

    #[Route('/quizzes/{id}', name: 'api_quiz_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, #[MapRequestPayload] QuizInput $input): JsonResponse
    {
        $quiz = $this->quizzes->find($id);

        if (!$quiz) {
            return $this->json(['error' => 'Quiz introuvable.'], 404);
        }

        if (!$this->identity->canEdit($quiz)) {
            return $this->json(['error' => 'Ce quiz a été rédigé par quelqu\'un d\'autre.'], 403);
        }

        $quiz = $this->writer->update($quiz, $input);

        return $this->json($this->normalizer->detail($quiz, true) + ['canEdit' => true]);
    }

    #[Route('/quizzes/{id}', name: 'api_quiz_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $quiz = $this->quizzes->find($id);

        if (!$quiz) {
            return $this->json(['error' => 'Quiz introuvable.'], 404);
        }

        if (!$this->identity->canEdit($quiz)) {
            return $this->json(['error' => 'Ce quiz a été rédigé par quelqu\'un d\'autre.'], 403);
        }

        // SQLite n'applique pas les clés étrangères : on détache et on nettoie à la main.
        $this->sessions->detachQuiz($quiz);
        $this->liveGames->removeForQuiz($quiz);
        $this->em->remove($quiz);
        $this->em->flush();

        return new JsonResponse(null, 204);
    }
}
