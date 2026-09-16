<?php

namespace App\Ai\Application;

use App\Ai\Domain\Model\AiKey;
use App\Ai\Domain\Repository\AiKeyRepository;
use App\Identity\Domain\Model\User;
use App\Shared\Application\AdminKeyGenerator;
use App\Shared\Application\UnitOfWork;
use Psr\Clock\ClockInterface;

/** Crée une clé IA depuis le back-office. */
class IssueAiKey
{
    public function __construct(
        private readonly AiKeyRepository $keys,
        private readonly AdminKeyGenerator $generator,
        private readonly ClockInterface $clock,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    /**
     * Sans $owner : un code à partager, saisi ensuite dans « Mon compte ».
     * Avec $owner : la clé est liée tout de suite à ce compte, sans code à transmettre.
     */
    public function issue(int $totalGenerations, ?string $label, ?int $expiresInDays, ?User $owner, string $createdBy): AiKey
    {
        $key = (new AiKey($this->uniqueValue(), $totalGenerations))
            ->setLabel(trim((string) $label) ?: null)
            ->setCreatedBy($createdBy);

        if (null !== $expiresInDays) {
            $key->setExpiresAt($this->clock->now()->modify(sprintf('+%d days', $expiresInDays)));
        }

        if (null !== $owner) {
            $key->redeemFor($owner);
        }

        $this->keys->add($key);
        $this->unitOfWork->flush();

        return $key;
    }

    /** Tire des codes au hasard jusqu'à en trouver un qui n'existe pas encore. */
    private function uniqueValue(): string
    {
        do {
            $value = $this->generator->generate();
        } while (null !== $this->keys->findByValue($value));

        return $value;
    }
}
