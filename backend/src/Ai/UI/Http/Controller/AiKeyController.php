<?php

namespace App\Ai\UI\Http\Controller;

use App\Ai\Domain\Model\AiKey;
use App\Ai\Domain\Repository\AiKeyRepository;
use App\Ai\UI\Http\Dto\AiKeyFilter;
use App\Ai\UI\Http\Dto\CreateAiKeyInput;
use App\Identity\Domain\Repository\UserRepository;
use App\Identity\Infrastructure\Security\CurrentUser;
use App\Shared\Application\AdminKeyGenerator;
use App\Shared\Application\UnitOfWork;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
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
        private readonly UnitOfWork $unitOfWork,
        private readonly CurrentUser $identity,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('', name: 'api_ai_key_list', methods: ['GET'])]
    public function list(#[MapQueryString] AiKeyFilter $filter = new AiKeyFilter()): JsonResponse
    {
        return $this->json(array_map($this->normalize(...), $this->keys->search($filter->search, $filter->status)));
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
            $user = $this->users->ofId($input->userId);

            if (!$user) {
                return $this->json(['error' => 'Compte introuvable.'], 404);
            }

            $key->redeemFor($user);
        }

        $this->keys->add($key);
        $this->unitOfWork->flush();

        return $this->json($this->normalize($key), 201);
    }

    /** On révoque plutôt que de supprimer : l'historique d'usage reste lisible. */
    #[Route('/{id}', name: 'api_ai_key_revoke', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function revoke(int $id): JsonResponse
    {
        $key = $this->keys->ofId($id);

        if (!$key) {
            return $this->json(['error' => 'Clé introuvable.'], 404);
        }

        $key->revoke();
        $this->unitOfWork->flush();

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
            'status' => match (true) {
                $key->isRevoked() => 'revoked',
                $expired => 'expired',
                $key->isExhausted() => 'exhausted',
                null === $key->getRedeemedBy() => 'unclaimed',
                default => 'active',
            },
            'redeemedBy' => $key->getRedeemedByName(),
            'redeemedAt' => $key->getRedeemedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
