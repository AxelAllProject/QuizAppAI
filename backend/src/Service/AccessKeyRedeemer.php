<?php

namespace App\Service;

use App\Entity\AccessKey;
use App\Repository\AccessKeyRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Traduit une clé d'accès en rôle, à l'inscription ou depuis « Mon compte ». */
class AccessKeyRedeemer
{
    public function __construct(
        #[Autowire('%env(ADMIN_CODE)%')]
        private readonly string $bootstrapKey,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        private readonly AccessKeyRepository $keys,
    ) {
    }

    /**
     * Rôle conféré par une clé enregistrée par un admin, ou par la clé de secours
     * définie dans l'environnement. Null si la clé est inconnue ou révoquée.
     * L'usage est compté mais pas enregistré : c'est à l'appelant de flusher.
     */
    public function redeem(#[\SensitiveParameter] string $code): ?string
    {
        $code = trim($code);

        if ('' === $code) {
            return null;
        }

        if ($key = $this->keys->findActive($code)) {
            $key->markUsed();

            return $key->getRole();
        }

        return $this->bootstrapKeyIsUsable() && hash_equals($this->bootstrapKey, $code) ? AccessKey::ROLE_ADMIN : null;
    }

    /**
     * « admin » est la valeur livrée par défaut dans .env (non secrète, committée) : parfaite
     * pour le développement, mais elle rendrait n'importe qui administrateur si un déploiement
     * oubliait de la remplacer dans .env.local. On la refuse donc explicitement en production.
     */
    private function bootstrapKeyIsUsable(): bool
    {
        return '' !== $this->bootstrapKey && !('prod' === $this->environment && 'admin' === $this->bootstrapKey);
    }
}
