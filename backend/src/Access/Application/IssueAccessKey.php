<?php

namespace App\Access\Application;

use App\Access\Domain\Model\AccessKey;
use App\Access\Domain\Repository\AccessKeyRepository;
use App\Identity\Domain\Model\User;
use App\Shared\Application\AdminKeyGenerator;
use App\Shared\Application\UnitOfWork;
use Psr\Clock\ClockInterface;

/** Émet une clé d'accès, depuis le back-office ou la ligne de commande. */
class IssueAccessKey
{
    public function __construct(
        private readonly AccessKeyRepository $keys,
        private readonly AdminKeyGenerator $generator,
        private readonly ClockInterface $clock,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    /**
     * Sans $assignee : un code à partager, saisi ensuite par la personne elle-même.
     * Avec $assignee : le rôle est accordé tout de suite à ce compte, sans code à transmettre.
     */
    public function issue(string $role, ?string $label, ?int $expiresInDays, ?User $assignee, string $createdBy): AccessKey
    {
        $key = (new AccessKey())
            ->setValue($this->uniqueValue())
            ->setRole($role)
            ->setLabel(trim((string) $label) ?: null)
            ->setCreatedBy($createdBy);

        if (null !== $expiresInDays) {
            $key->setExpiresAt($this->clock->now()->modify(sprintf('+%d days', $expiresInDays)));
        }

        if (null !== $assignee) {
            $key->assignTo($assignee);
        }

        $this->keys->add($key);
        $this->unitOfWork->flush();

        return $key;
    }

    /** Le générateur est aléatoire, mais l'unicité en base reste garantie explicitement. */
    private function uniqueValue(): string
    {
        do {
            $value = $this->generator->generate();
        } while (null !== $this->keys->ofValue($value));

        return $value;
    }
}
