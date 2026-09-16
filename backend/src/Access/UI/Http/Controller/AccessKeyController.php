<?php

namespace App\Access\UI\Http\Controller;

use App\Access\Domain\Repository\AccessKeyRepository;
use App\Access\UI\Http\AccessKeyNormalizer;
use App\Access\UI\Http\Dto\AccessKeyFilter;
use App\Shared\Application\UnitOfWork;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Gestion des clés d'accès : réservée aux administrateurs. */
#[Route('/api/access-keys')]
#[IsGranted('ROLE_ADMIN', message: 'Réservé aux administrateurs.')]
class AccessKeyController extends AbstractController
{
    public function __construct(
        private readonly AccessKeyRepository $keys,
        private readonly AccessKeyNormalizer $normalizer,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    /** Le filtrage est fait en base : le back-office pagine sur un jeu de clés déjà réduit. */
    #[Route('', name: 'api_access_key_list', methods: ['GET'])]
    public function list(#[MapQueryString] AccessKeyFilter $filter = new AccessKeyFilter()): JsonResponse
    {
        return $this->json(array_map($this->normalizer->normalize(...), $this->keys->search($filter->search, $filter->role, $filter->status)));
    }

    /** On révoque plutôt que de supprimer : l'historique d'usage reste lisible. */
    #[Route('/{id}', name: 'api_access_key_revoke', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function revoke(int $id): JsonResponse
    {
        $key = $this->keys->ofId($id) ?? throw $this->createNotFoundException('Clé introuvable.');
        $key->revoke();
        $this->unitOfWork->flush();

        return $this->json($this->normalizer->normalize($key));
    }
}
