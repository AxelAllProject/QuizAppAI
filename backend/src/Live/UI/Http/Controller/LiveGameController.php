<?php

namespace App\Live\UI\Http\Controller;

use App\Identity\Domain\Model\User;
use App\Live\Application\LiveGameEngine;
use App\Live\Application\LiveGameNormalizer;
use App\Live\Domain\Exception\LiveGameException;
use App\Live\Domain\Model\LiveGame;
use App\Live\Domain\Repository\LiveGameRepository;
use App\Live\UI\Http\Dto\LiveAnswerInput;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Parties en direct : l'animateur crée la partie, les joueurs la rejoignent avec le code PIN. */
#[Route('/api/live-games')]
class LiveGameController extends AbstractController
{
    public function __construct(
        private readonly LiveGameRepository $games,
        private readonly LiveGameEngine $engine,
        private readonly LiveGameNormalizer $normalizer,
    ) {
    }

    /** Interrogé chaque seconde par l'écran de l'animateur et par celui de chaque joueur. */
    #[Route('/{pin}', name: 'api_live_state', methods: ['GET'], requirements: ['pin' => '\d{6}'])]
    public function state(string $pin, #[CurrentUser] User $user): JsonResponse
    {
        if (!$game = $this->games->findLatestByPin($pin)) {
            return $this->notFound();
        }

        $this->engine->refresh($game);
        $player = $this->engine->playerOf($game, $user);

        if (!$this->isHost($game, $user) && !$player) {
            return $this->json(['error' => 'Rejoins d’abord la partie avec son code PIN.'], 403);
        }

        return $this->json($this->normalizer->state($game, $player, $this->isHost($game, $user)));
    }

    /** Fait rejoindre la partie au compte connecté. */
    #[Route('/{pin}/join', name: 'api_live_join', methods: ['POST'], requirements: ['pin' => '\d{6}'])]
    public function join(string $pin, #[CurrentUser] User $user): JsonResponse
    {
        if (!$game = $this->games->findLatestByPin($pin)) {
            return $this->notFound();
        }

        try {
            $player = $this->engine->join($game, $user);
        } catch (LiveGameException $exception) {
            return $this->json(['error' => $exception->getMessage()], 409);
        }

        return $this->json($this->normalizer->state($game, $player, false));
    }

    /** Fait passer la partie à l'étape suivante (réservé à l'animateur). */
    #[Route('/{pin}/next', name: 'api_live_next', methods: ['POST'], requirements: ['pin' => '\d{6}'])]
    public function next(string $pin, #[CurrentUser] User $user): JsonResponse
    {
        if (!$game = $this->games->findLatestByPin($pin)) {
            return $this->notFound();
        }

        if (!$this->isHost($game, $user)) {
            return $this->json(['error' => 'Seul l’animateur fait avancer la partie.'], 403);
        }

        try {
            $this->engine->advance($game);
        } catch (LiveGameException $exception) {
            return $this->json(['error' => $exception->getMessage()], 409);
        }

        return $this->json($this->normalizer->state($game, null, true));
    }

    /** Enregistre la réponse du joueur connecté à la question en cours. */
    #[Route('/{pin}/answers', name: 'api_live_answer', methods: ['POST'], requirements: ['pin' => '\d{6}'])]
    public function answer(string $pin, #[MapRequestPayload] LiveAnswerInput $input, #[CurrentUser] User $user): JsonResponse
    {
        if (!$game = $this->games->findLatestByPin($pin)) {
            return $this->notFound();
        }

        if (!$player = $this->engine->playerOf($game, $user)) {
            return $this->json(['error' => 'Rejoins d’abord la partie avec son code PIN.'], 403);
        }

        try {
            $this->engine->answer($game, $player, $input->choiceIndex);
        } catch (LiveGameException $exception) {
            return $this->json(['error' => $exception->getMessage()], 409);
        }

        return $this->json($this->normalizer->state($game, $player, false));
    }

    /** Arrête la partie (réservé à l'animateur). */
    #[Route('/{pin}', name: 'api_live_stop', methods: ['DELETE'], requirements: ['pin' => '\d{6}'])]
    public function stop(string $pin, #[CurrentUser] User $user): JsonResponse
    {
        if (!$game = $this->games->findLatestByPin($pin)) {
            return $this->notFound();
        }

        if (!$this->isHost($game, $user)) {
            return $this->json(['error' => 'Seul l’animateur peut arrêter la partie.'], 403);
        }

        $this->engine->stop($game);

        return $this->json($this->normalizer->state($game, null, true));
    }

    /** Indique si le compte connecté anime la partie. */
    private function isHost(LiveGame $game, User $user): bool
    {
        return $game->getHost()->getId() === $user->getId();
    }

    /** Réponse 404 quand aucune partie ne correspond au code PIN. */
    private function notFound(): JsonResponse
    {
        return $this->json(['error' => 'Aucune partie avec ce code PIN.'], 404);
    }
}
