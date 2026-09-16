<?php

namespace App\Ai\UI\Http\Controller;

use App\Ai\Application\IssueAiKey;
use App\Ai\UI\Http\AiKeyNormalizer;
use App\Ai\UI\Http\Dto\CreateAiKeyInput;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN', message: 'Réservé aux administrateurs.')]
class IssueAiKeyController extends AbstractController
{
    public function __construct(
        private readonly IssueAiKey $issueAiKey,
        private readonly UserRepository $users,
        private readonly AiKeyNormalizer $normalizer,
    ) {
    }

    /**
     * Sans `userId` : génère un code à partager, saisi ensuite par la personne elle-même
     * dans « Mon compte ». Avec `userId` : lie la clé tout de suite au compte choisi
     * (recherché dans la liste des comptes), sans code à transmettre.
     */
    #[Route('/api/ai-keys', name: 'api_ai_key_create', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] CreateAiKeyInput $input, #[CurrentUser] User $admin): JsonResponse
    {
        $owner = null === $input->userId ? null : ($this->users->ofId($input->userId) ?? throw $this->createNotFoundException('Compte introuvable.'));
        $key = $this->issueAiKey->issue($input->totalGenerations, $input->label, $input->expiresInDays, $owner, $admin->getUsername());

        return $this->json($this->normalizer->normalize($key), 201);
    }
}
