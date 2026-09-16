<?php

namespace App\Ai\Application;

use App\Ai\Domain\Exception\AiKeyRedemptionException;
use App\Ai\Domain\Model\AiKey;
use App\Ai\Domain\Repository\AiKeyRepository;
use App\Identity\Domain\Model\User;
use App\Shared\Application\UnitOfWork;
use Psr\Clock\ClockInterface;

/**
 * Débloque la génération de quiz par IA : la clé se lie au premier compte qui la
 * saisit, et ne peut plus être utilisée par un autre après ça.
 */
class AiKeyRedeemer
{
    public function __construct(
        private readonly AiKeyRepository $keys,
        private readonly ClockInterface $clock,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    /** @throws AiKeyRedemptionException */
    public function redeem(#[\SensitiveParameter] string $value, User $user): AiKey
    {
        $key = $this->keys->findByValue($value);

        if (!$key || $key->isRevoked() || $key->isExpired($this->clock->now())) {
            throw new AiKeyRedemptionException('Clé IA invalide, révoquée ou expirée.', 403);
        }

        if ($key->isRedeemedBySomeoneElse($user)) {
            throw new AiKeyRedemptionException('Cette clé IA a déjà été utilisée par quelqu’un d’autre.', 409);
        }

        if ($key->isExhausted()) {
            throw new AiKeyRedemptionException('Cette clé IA n’a plus de génération disponible.', 409);
        }

        $key->redeemFor($user);
        $this->unitOfWork->flush();

        return $key;
    }
}
