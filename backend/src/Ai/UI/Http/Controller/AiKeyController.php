<?php

namespace App\Ai\UI\Http\Controller;

use App\Ai\Domain\Repository\AiKeyRepository;
use App\Ai\UI\Http\AiKeyNormalizer;
use App\Ai\UI\Http\Dto\AiKeyFilter;
use App\Shared\Application\UnitOfWork;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Clés IA : la génération de quiz par IA est une fonctionnalité premium, distincte du
 * rôle professeur/admin — seul un administrateur peut émettre les clés qui la débloquent.
 */
#[Route('/api/ai-keys')]
#[IsGranted('ROLE_ADMIN', message: 'Réservé aux administrateurs.')]
class AiKeyController extends AbstractController
{
    public function __construct(
        private readonly AiKeyRepository $keys,
        private readonly AiKeyNormalizer $normalizer,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    /** Liste les clés IA, filtrées en base par recherche et par état. */
    #[Route('', name: 'api_ai_key_list', methods: ['GET'])]
    public function list(#[MapQueryString] AiKeyFilter $filter = new AiKeyFilter()): JsonResponse
    {
        return $this->json(array_map($this->normalizer->normalize(...), $this->keys->search($filter->search, $filter->status)));
    }

    /** On révoque plutôt que de supprimer : l'historique d'usage reste lisible. */
    #[Route('/{id}', name: 'api_ai_key_revoke', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function revoke(int $id): JsonResponse
    {
        $key = $this->keys->ofId($id) ?? throw $this->createNotFoundException('Clé introuvable.');
        $key->revoke();
        $this->unitOfWork->flush();

        return $this->json($this->normalizer->normalize($key));
    }
}
