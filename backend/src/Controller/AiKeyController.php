<?php

namespace App\Controller;

use App\Dto\CreateAiKeyInput;
use App\Entity\AiKey;
use App\Repository\AiKeyRepository;
use App\Repository\UserRepository;
use App\Service\AdminKeyGenerator;
use App\Service\Identity;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
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
        private readonly UserRepository $users,
        private readonly AdminKeyGenerator $generator,
        private readonly EntityManagerInterface $em,
        private readonly Identity $identity,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('', name: 'api_ai_key_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json(array_map($this->normalize(...), $this->keys->findAllOrdered()));
    }

    /**
     * Sans `userId` : génère un code à partager, saisi ensuite par la personne elle-même
     * dans « Mon compte ». Avec `userId` : lie la clé tout de suite au compte choisi
     * (recherché dans la liste des comptes), sans code à transmettre.
     */
    #[Route('', name: 'api_ai_key_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateAiKeyInput $input): JsonResponse
    {
        $key = (new AiKey($this->uniqueValue(), $input->totalGenerations))
            ->setLabel(trim((string) $input->label) ?: null)
            ->setCreatedBy($this->identity->name());

        if (null !== $input->expiresInDays) {
            $key->setExpiresAt($this->clock->now()->modify(sprintf('+%d days', $input->expiresInDays)));
        }

        if (null !== $input->userId) {
            $user = $this->users->find($input->userId);

            if (!$user) {
                return $this->json(['error' => 'Compte introuvable.'], 404);
            }

            $key->redeemFor($user);
        }

        $this->em->persist($key);
        $this->em->flush();

        return $this->json($this->normalize($key), 201);
    }

    /** On révoque plutôt que de supprimer : l'historique d'usage reste lisible. */
    #[Route('/{id}', name: 'api_ai_key_revoke', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function revoke(int $id): JsonResponse
    {
        $key = $this->keys->find($id);

        if (!$key) {
            return $this->json(['error' => 'Clé introuvable.'], 404);
        }

        $key->revoke();
        $this->em->flush();

        return $this->json($this->normalize($key));
    }

    private function uniqueValue(): string
    {
        do {
            $value = $this->generator->generate();
        } while (null !== $this->keys->findByValue($value));

        return $value;
    }

    private function normalize(AiKey $key): array
    {
        $expired = $key->isExpired($this->clock->now());

        return [
            'id' => $key->getId(),
            'value' => $key->getValue(),
            'label' => $key->getLabel(),
            'totalGenerations' => $key->getTotalGenerations(),
            'remainingGenerations' => $key->getRemainingGenerations(),
            'createdBy' => $key->getCreatedBy(),
            'createdAt' => $key->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'expiresAt' => $key->getExpiresAt()?->format(\DateTimeInterface::ATOM),
            'revokedAt' => $key->getRevokedAt()?->format(\DateTimeInterface::ATOM),
            'active' => !$key->isRevoked() && !$expired && !$key->isExhausted(),
            'expired' => $expired,
            'redeemedBy' => $key->getRedeemedByName(),
            'redeemedAt' => $key->getRedeemedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
