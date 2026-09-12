<?php

namespace App\Controller;

use App\Dto\RoleInput;
use App\Entity\User;
use App\Repository\GameSessionRepository;
use App\Repository\UserRepository;
use App\Service\Identity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Annuaire des comptes, réservé à l'administration. L'adresse e-mail n'y figure
 * pas : l'admin n'en a pas besoin pour gérer les rôles (minimisation).
 */
#[Route('/api/users')]
#[IsGranted('ROLE_ADMIN', message: 'Réservé aux administrateurs.')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly GameSessionRepository $sessions,
        private readonly Identity $identity,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'api_user_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $stats = [];

        foreach ($this->sessions->findHistory(null, 1000) as $session) {
            if (!$userId = $session->getUser()?->getId()) {
                continue;
            }

            $entry = $stats[$userId] ?? ['games' => 0, 'score' => 0, 'total' => 0];
            ++$entry['games'];
            $entry['score'] += $session->getScore();
            $entry['total'] += $session->getTotal();
            $stats[$userId] = $entry;
        }

        return $this->json(array_map(
            fn (User $user) => $this->normalize($user, $stats[$user->getId()] ?? null),
            $this->users->findAllOrdered(),
        ));
    }

    /** Retirer un rôle : une clé révoquée n'enlève pas les droits déjà accordés, ceci si. */
    #[Route('/{id}/role', name: 'api_user_role', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function changeRole(int $id, #[MapRequestPayload] RoleInput $input): JsonResponse
    {
        $user = $this->users->find($id);

        if (!$user) {
            return $this->json(['error' => 'Compte introuvable.'], 404);
        }

        if ($user->getId() === $this->identity->user()->getId() && User::ROLE_ADMIN !== $input->role) {
            return $this->json(['error' => 'Tu ne peux pas retirer tes propres droits d’administrateur.'], 409);
        }

        $user->setRole($input->role);
        $this->em->flush();

        return $this->json($this->normalize($user, null));
    }

    /** @param array{games: int, score: int, total: int}|null $stats */
    private function normalize(User $user, ?array $stats): array
    {
        return [
            'id' => $user->getId(),
            'name' => $user->getUsername(),
            'role' => $user->getRole(),
            'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'lastSeenAt' => $user->getLastSeenAt()->format(\DateTimeInterface::ATOM),
            'games' => $stats['games'] ?? 0,
            'accuracy' => ($stats['total'] ?? 0) > 0 ? (int) round($stats['score'] / $stats['total'] * 100) : null,
        ];
    }
}
