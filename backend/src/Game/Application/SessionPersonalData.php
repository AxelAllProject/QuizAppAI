<?php

namespace App\Game\Application;

use App\Game\Domain\Model\GameSession;
use App\Game\Domain\Repository\GameSessionRepository;
use App\Identity\Application\PersonalDataEraser;
use App\Identity\Application\PersonalDataExporter;
use App\Identity\Domain\Model\User;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/** Historique des parties solo : exporté, puis effacé avec le compte. */
#[AsTaggedItem(priority: 20)]
class SessionPersonalData implements PersonalDataExporter, PersonalDataEraser
{
    public function __construct(
        private readonly GameSessionRepository $sessions,
        private readonly SessionNormalizer $normalizer,
    ) {
    }

    /** Exporte toutes les parties solo du compte. */
    public function export(User $user): array
    {
        return ['sessions' => array_map(
            fn (GameSession $session) => $this->normalizer->session($session),
            $this->sessions->findHistory($user, null),
        )];
    }

    /** Supprime toutes les parties solo du compte. */
    public function erase(User $user): void
    {
        $this->sessions->deleteByUser($user);
    }
}
