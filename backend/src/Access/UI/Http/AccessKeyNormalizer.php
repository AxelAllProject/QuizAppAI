<?php

namespace App\Access\UI\Http;

use App\Access\Domain\Model\AccessKey;
use Psr\Clock\ClockInterface;

class AccessKeyNormalizer
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function normalize(AccessKey $key): array
    {
        $now = $this->clock->now();

        return [
            'id' => $key->getId(),
            'value' => $key->getValue(),
            'role' => $key->getRole(),
            'label' => $key->getLabel(),
            'createdBy' => $key->getCreatedBy(),
            'createdAt' => $key->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'revokedAt' => $key->getRevokedAt()?->format(\DateTimeInterface::ATOM),
            'expiresAt' => $key->getExpiresAt()?->format(\DateTimeInterface::ATOM),
            'expired' => $key->isExpired($now),
            'status' => $key->status($now),
            'active' => $key->isUsable($now),
            'usageCount' => $key->getUsageCount(),
            'lastUsedAt' => $key->getLastUsedAt()?->format(\DateTimeInterface::ATOM),
            'assignedTo' => $key->getAssignedToName(),
        ];
    }
}
