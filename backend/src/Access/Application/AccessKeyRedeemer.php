<?php

namespace App\Access\Application;

use App\Access\Domain\Model\AccessKey;
use App\Access\Domain\Repository\AccessKeyRepository;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Applique le rôle d'une clé d'accès à un compte, à l'inscription ou depuis « Mon compte ». */
class AccessKeyRedeemer
{
    public function __construct(
        #[Autowire('%env(ADMIN_CODE)%')]
        private readonly string $bootstrapKey,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        private readonly AccessKeyRepository $keys,
        private readonly UserRepository $users,
    ) {
    }

    /**
     * Promeut le compte selon une clé enregistrée par un admin, ou selon la clé de secours
     * définie dans l'environnement. Renvoie false si la clé est inconnue, révoquée, périmée
     * ou — pour une clé admin — déjà utilisée.
     *
     * Une clé prof se partage (toute une équipe pédagogique peut saisir la même) ; une clé
     * admin, non : elle se lie au premier compte qui la saisit, comme une attribution directe.
     * Sans ça, un code admin qui circule ferait administrateur quiconque le récupère.
     *
     * Rien n'est flushé, c'est à l'appelant de le faire — sauf la réservation d'une clé admin,
     * qui doit être immédiate pour que deux saisies simultanées ne passent pas toutes les deux.
     */
    public function redeem(#[\SensitiveParameter] string $code, User $user): bool
    {
        $code = trim($code);

        if ('' === $code) {
            return false;
        }

        if ($key = $this->keys->findActive($code)) {
            if (AccessKey::ROLE_ADMIN !== $key->getRole()) {
                $key->markUsed();
                $user->promote($key->getRole());

                return true;
            }

            if (!$this->keys->claim($key)) {
                return false;
            }

            $key->assignTo($user);

            return true;
        }

        if (!hash_equals($this->bootstrapKey, $code) || !$this->bootstrapKeyIsUsable()) {
            return false;
        }

        $user->promote(User::ROLE_ADMIN);

        return true;
    }

    /**
     * La clé de secours ne sert qu'à créer le premier administrateur : ensuite, les admins
     * émettent des clés révocables depuis le back-office, et elle ne doit plus rester une
     * porte d'entrée permanente.
     *
     * « admin » est en outre la valeur livrée par défaut dans .env (non secrète, committée) :
     * on la refuse explicitement en production, au cas où un déploiement oublierait de la remplacer.
     */
    private function bootstrapKeyIsUsable(): bool
    {
        if ('' === $this->bootstrapKey || ('prod' === $this->environment && 'admin' === $this->bootstrapKey)) {
            return false;
        }

        return !$this->users->hasAdmin();
    }
}
