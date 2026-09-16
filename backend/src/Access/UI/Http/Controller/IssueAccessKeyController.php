<?php

namespace App\Access\UI\Http\Controller;

use App\Access\Application\IssueAccessKey;
use App\Access\UI\Http\AccessKeyNormalizer;
use App\Access\UI\Http\Dto\AccessKeyInput;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN', message: 'Réservé aux administrateurs.')]
class IssueAccessKeyController extends AbstractController
{
    public function __construct(
        private readonly IssueAccessKey $issueAccessKey,
        private readonly UserRepository $users,
        private readonly AccessKeyNormalizer $normalizer,
    ) {
    }

    /**
     * Sans `userId` : génère un code à partager, saisi ensuite par la personne elle-même.
     * Avec `userId` : accorde le rôle tout de suite au compte choisi (recherché dans la
     * liste des comptes), sans code à transmettre.
     */
    #[Route('/api/access-keys', name: 'api_access_key_create', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] AccessKeyInput $input, #[CurrentUser] User $admin): JsonResponse
    {
        $assignee = null === $input->userId ? null : ($this->users->ofId($input->userId) ?? throw $this->createNotFoundException('Compte introuvable.'));
        $key = $this->issueAccessKey->issue($input->role, $input->label, $input->expiresInDays, $assignee, $admin->getUsername());

        return $this->json($this->normalizer->normalize($key), 201);
    }
}
