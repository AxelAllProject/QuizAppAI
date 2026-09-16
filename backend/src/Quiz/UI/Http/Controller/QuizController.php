<?php

namespace App\Quiz\UI\Http\Controller;

use App\Identity\Domain\Model\User;
use App\Quiz\Application\DeleteQuiz;
use App\Quiz\Application\Dto\QuizInput;
use App\Quiz\Application\QuizNormalizer;
use App\Quiz\Application\QuizWriter;
use App\Quiz\Domain\Model\Quiz;
use App\Quiz\Domain\Repository\QuizRepository;
use App\Quiz\Infrastructure\Security\QuizVoter;
use App\Quiz\UI\Http\Dto\QuizDetailQuery;
use App\Quiz\UI\Http\Dto\QuizFilter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Routes de la bibliothèque et de l'éditeur de quiz. */
#[Route('/api')]
class QuizController extends AbstractController
{
    private const NOT_OWNER = 'Ce quiz a été rédigé par quelqu\'un d\'autre.';

    public function __construct(
        private readonly QuizRepository $quizzes,
        private readonly QuizNormalizer $normalizer,
        private readonly QuizWriter $writer,
        private readonly DeleteQuiz $deleteQuiz,
    ) {
    }

    /** Liste les quiz filtrés, en indiquant pour chacun si le compte connecté peut le modifier. */
    #[Route('/quizzes', name: 'api_quiz_list', methods: ['GET'])]
    public function list(#[CurrentUser] User $user, #[MapQueryString] QuizFilter $filter = new QuizFilter()): JsonResponse
    {
        $found = $this->quizzes->search($filter->search, $filter->category, $filter->mine ? $user : null);

        return $this->json(array_map(
            fn (Quiz $quiz) => $this->normalizer->summary($quiz) + ['canEdit' => $this->isGranted(QuizVoter::EDIT, $quiz)],
            $found,
        ));
    }

    /** Liste les catégories existantes. */
    #[Route('/categories', name: 'api_categories', methods: ['GET'])]
    public function categories(): JsonResponse
    {
        return $this->json($this->quizzes->findCategories());
    }

    /** Affiche un quiz, avec les bonnes réponses seulement pour qui peut le modifier. */
    #[Route('/quizzes/{id}', name: 'api_quiz_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, #[MapQueryString] QuizDetailQuery $query = new QuizDetailQuery()): JsonResponse
    {
        $quiz = $this->find($id);
        $canEdit = $this->isGranted(QuizVoter::EDIT, $quiz);
        // Les bonnes réponses ne sortent que pour l'édition, jamais pour jouer.
        $withAnswers = $query->withAnswers && $canEdit;

        return $this->json($this->normalizer->detail($quiz, $withAnswers) + ['canEdit' => $canEdit]);
    }

    /** Crée un quiz pour le compte connecté (professeur ou admin). */
    #[Route('/quizzes', name: 'api_quiz_create', methods: ['POST'])]
    #[IsGranted('ROLE_TEACHER', message: 'Il faut être professeur ou administrateur pour créer un quiz.')]
    public function create(#[MapRequestPayload] QuizInput $input, #[CurrentUser] User $user): JsonResponse
    {
        $quiz = $this->writer->create($input, $user, $user->getUsername());

        return $this->json($this->normalizer->detail($quiz, true) + ['canEdit' => true], 201);
    }

    /** Modifie un quiz, si le compte connecté en a le droit. */
    #[Route('/quizzes/{id}', name: 'api_quiz_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, #[MapRequestPayload] QuizInput $input): JsonResponse
    {
        $quiz = $this->find($id);

        if (!$this->isGranted(QuizVoter::EDIT, $quiz)) {
            return $this->json(['error' => self::NOT_OWNER], 403);
        }

        return $this->json($this->normalizer->detail($this->writer->update($quiz, $input), true) + ['canEdit' => true]);
    }

    /** Supprime un quiz, si le compte connecté en a le droit. */
    #[Route('/quizzes/{id}', name: 'api_quiz_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $quiz = $this->find($id);

        if (!$this->isGranted(QuizVoter::EDIT, $quiz)) {
            return $this->json(['error' => self::NOT_OWNER], 403);
        }

        $this->deleteQuiz->delete($quiz);

        return new JsonResponse(null, 204);
    }

    /** Récupère le quiz demandé, ou renvoie une erreur 404. */
    private function find(int $id): Quiz
    {
        return $this->quizzes->ofId($id) ?? throw $this->createNotFoundException('Quiz introuvable.');
    }
}
