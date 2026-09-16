<?php

namespace App\Ai\UI\Http;

use App\Ai\Domain\Model\AiKey;
use Psr\Clock\ClockInterface;

class AiKeyNormalizer
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function normalize(AiKey $key): array
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
