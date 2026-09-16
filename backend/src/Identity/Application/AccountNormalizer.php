<?php

namespace App\Identity\Application;

use App\Ai\Domain\Model\AiKey;
use App\Ai\Domain\Repository\AiKeyRepository;
use App\Identity\Domain\Model\User;

class AccountNormalizer
{
    public function __construct(private readonly AiKeyRepository $aiKeys)
    {
    }

    public function me(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getUsername(),
            'role' => $user->getRole(),
            'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'consentedAt' => $user->getConsentedAt()?->format(\DateTimeInterface::ATOM),
            'privacyPolicyVersion' => $user->getConsentVersion(),
            'aiKey' => ($key = $this->aiKeys->findActiveFor($user)) ? $this->aiKey($key) : null,
        ];
    }

    private function aiKey(AiKey $key): array
    {
        return [
            'remainingGenerations' => $key->getRemainingGenerations(),
            'totalGenerations' => $key->getTotalGenerations(),
            'expiresAt' => $key->getExpiresAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
