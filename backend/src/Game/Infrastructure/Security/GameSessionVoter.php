<?php

namespace App\Game\Infrastructure\Security;

use App\Game\Domain\Model\GameSession;
use App\Identity\Domain\Model\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, GameSession> */
class GameSessionVoter extends Voter
{
    public const VIEW = 'GAME_SESSION_VIEW';

    public function __construct(private readonly AccessDecisionManagerInterface $accessDecisionManager)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::VIEW === $attribute && $subject instanceof GameSession;
    }

    /** Sa propre partie ; un admin voit toutes les parties. */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        $user = $token->getUser();

        return $user instanceof User && $subject->getUser()?->getId() === $user->getId();
    }
}
