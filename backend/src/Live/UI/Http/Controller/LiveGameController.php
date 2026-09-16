<?php

namespace App\Live\UI\Http\Controller;

use App\Identity\Infrastructure\Security\CurrentUser;
use App\Live\Application\LiveGameEngine;
use App\Live\Application\LiveGameNormalizer;
use App\Live\Domain\Exception\LiveGameException;
use App\Live\Domain\Model\LiveGame;
use App\Live\Domain\Repository\LiveGameRepository;
use App\Live\UI\Http\Dto\LiveAnswerInput;
use App\Live\UI\Http\Dto\LiveGameInput;
use App\Quiz\Domain\Repository\QuizRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Parties en direct : l'animateur crée la partie, les joueurs la rejoignent avec le code PIN. */
#[Route('/api/live-games')]
class LiveGameController extends AbstractController
{
    public function __construct(
        private readonly LiveGameRepository $games,
        private readonly QuizRepository $quizzes,
        private readonly LiveGameEngine $engine,
        private readonly LiveGameNormalizer $normalizer,
        private readonly CurrentUser $identity,
    ) {
    }

    #[Route('', name: 'api_live_create', methods: ['POST'])]
    #[IsGranted('ROLE_TEACHER', message: 'Il faut être professeur ou administrateur pour animer une partie.')]
    public function create(#[MapRequestPayload] LiveGameInput $input): JsonResponse
    {
        $quiz = $this->quizzes->ofId($input->quizId);

        if (!$quiz) {
            return $this->json(['error' => 'Quiz introuvable.'], 404);
        }

        $game = $this->engine->create($quiz, $this->identity->user());

        return $this->json($this->normalizer->state($game, null, true), 201);
    }

    /** Interrogé chaque seconde par l'écran de l'animateur et par celui de chaque joueur. */
    #[Route('/{pin}', name: 'api_live_state', methods: ['GET'], requirements: ['pin' => '\d{6}'])]
    public function state(string $pin): JsonResponse
    {
        if (!$game = $this->games->findLatestByPin($pin)) {
            return $this->notFound();
        }

        $this->engine->refresh($game);
        $player = $this->engine->playerOf($game, $this->identity->user());

        if (!$this->isHost($game) && !$player) {
            return $this->json(['error' => 'Rejoins d’abord la partie avec son code PIN.'], 403);
        }

        return $this->json($this->normalizer->state($game, $player, $this->isHost($game)));
    }

    #[Route('/{pin}/join', name: 'api_live_join', methods: ['POST'], requirements: ['pin' => '\d{6}'])]
    public function join(string $pin): JsonResponse
    {
        if (!$game = $this->games->findLatestByPin($pin)) {
            return $this->notFound();
        }

        try {
            $player = $this->engine->join($game, $this->identity->user());
        } catch (LiveGameException $exception) {
            return $this->json(['error' => $exception->getMessage()], 409);
        }

        return $this->json($this->normalizer->state($game, $player, false));
    }

    #[Route('/{pin}/next', name: 'api_live_next', methods: ['POST'], requirements: ['pin' => '\d{6}'])]
    public function next(string $pin): JsonResponse
    {
        if (!$game = $this->games->findLatestByPin($pin)) {
            return $this->notFound();
        }

        if (!$this->isHost($game)) {
            return $this->json(['error' => 'Seul l’animateur fait avancer la partie.'], 403);
        }

        try {
            $this->engine->advance($game);
        } catch (LiveGameException $exception) {
            return $this->json(['error' => $exception->getMessage()], 409);
        }

        return $this->json($this->normalizer->state($game, null, true));
    }

    #[Route('/{pin}/answers', name: 'api_live_answer', methods: ['POST'], requirements: ['pin' => '\d{6}'])]
    public function answer(string $pin, #[MapRequestPayload] LiveAnswerInput $input): JsonResponse
    {
        if (!$game = $this->games->findLatestByPin($pin)) {
            return $this->notFound();
        }

        if (!$player = $this->engine->playerOf($game, $this->identity->user())) {
            return $this->json(['error' => 'Rejoins d’abord la partie avec son code PIN.'], 403);
        }

        try {
            $this->engine->answer($game, $player, $input->choiceIndex);
        } catch (LiveGameException $exception) {
            return $this->json(['error' => $exception->getMessage()], 409);
        }

        return $this->json($this->normalizer->state($game, $player, false));
    }

    #[Route('/{pin}', name: 'api_live_stop', methods: ['DELETE'], requirements: ['pin' => '\d{6}'])]
    public function stop(string $pin): JsonResponse
    {
        if (!$game = $this->games->findLatestByPin($pin)) {
            return $this->notFound();
        }

        if (!$this->isHost($game)) {
            return $this->json(['error' => 'Seul l’animateur peut arrêter la partie.'], 403);
        }

        $this->engine->stop($game);

        return $this->json($this->normalizer->state($game, null, true));
    }

    private function isHost(LiveGame $game): bool
    {
        return $game->getHost()->getId() === $this->identity->user()?->getId();
    }

    private function notFound(): JsonResponse
    {
        return $this->json(['error' => 'Aucune partie avec ce code PIN.'], 404);
    }
}
