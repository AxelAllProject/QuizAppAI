<?php

namespace App\Controller;

use App\Dto\AccessKeyFilter;
use App\Dto\AccessKeyInput;
use App\Entity\AccessKey;
use App\Repository\AccessKeyRepository;
use App\Repository\UserRepository;
use App\Service\AdminKeyGenerator;
use App\Service\Identity;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Gestion des clés d'accès : réservée aux administrateurs. */
#[Route('/api/access-keys')]
#[IsGranted('ROLE_ADMIN', message: 'Réservé aux administrateurs.')]
class AccessKeyController extends AbstractController
{
    public function __construct(
        private readonly AccessKeyRepository $keys,
        private readonly UserRepository $users,
        private readonly AdminKeyGenerator $generator,
        private readonly EntityManagerInterface $em,
        private readonly Identity $identity,
        private readonly ClockInterface $clock,
    ) {
    }

    /** Le filtrage est fait en base : le back-office pagine sur un jeu de clés déjà réduit. */
    #[Route('', name: 'api_access_key_list', methods: ['GET'])]
    public function list(#[MapQueryString] AccessKeyFilter $filter = new AccessKeyFilter()): JsonResponse
    {
        $keys = $this->keys->search($filter->search, $filter->role, $filter->status);

        return $this->json(array_map($this->normalize(...), $keys));
    }

    /**
     * Sans `userId` : génère un code à partager, saisi ensuite par la personne elle-même.
     * Avec `userId` : accorde le rôle tout de suite au compte choisi (recherché dans la
     * liste des comptes), sans code à transmettre.
     */
    #[Route('', name: 'api_access_key_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] AccessKeyInput $input): JsonResponse
    {
        $key = (new AccessKey())
            ->setValue($this->uniqueValue())
            ->setRole($input->role)
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

            $key->assignTo($user);
        }

        $this->em->persist($key);
        $this->em->flush();

        return $this->json($this->normalize($key), 201);
    }

    /** On révoque plutôt que de supprimer : l'historique d'usage reste lisible. */
    #[Route('/{id}', name: 'api_access_key_revoke', methods: ['DELETE'], requirements: ['id' => '\d+'])]
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

    /** Le générateur est aléatoire, mais l'unicité en base reste garantie explicitement. */
    private function uniqueValue(): string
    {
        do {
            $value = $this->generator->generate();
        } while (null !== $this->keys->findOneBy(['value' => $value]));

        return $value;
    }

    private function normalize(AccessKey $key): array
    {
        return [
            'id' => $key->getId(),
            'value' => $key->getValue(),
            'role' => $key->getRole(),
            'label' => $key->getLabel(),
            'createdBy' => $key->getCreatedBy(),
            'createdAt' => $key->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'revokedAt' => $key->getRevokedAt()?->format(\DateTimeInterface::ATOM),
            'expiresAt' => $key->getExpiresAt()?->format(\DateTimeInterface::ATOM),
            'expired' => $key->isExpired($this->clock->now()),
            'status' => $key->status($this->clock->now()),
            'active' => $key->isUsable($this->clock->now()),
            'usageCount' => $key->getUsageCount(),
            'lastUsedAt' => $key->getLastUsedAt()?->format(\DateTimeInterface::ATOM),
            'assignedTo' => $key->getAssignedToName(),
        ];
    }
}
