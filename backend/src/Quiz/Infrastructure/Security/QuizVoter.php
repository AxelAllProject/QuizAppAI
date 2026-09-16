<?php

namespace App\Quiz\Infrastructure\Security;

use App\Identity\Domain\Model\User;
use App\Quiz\Domain\Model\Quiz;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, Quiz> */
class QuizVoter extends Voter
{
    public const EDIT = 'QUIZ_EDIT';

    public function __construct(private readonly AccessDecisionManagerInterface $accessDecisionManager)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::EDIT === $attribute && $subject instanceof Quiz;
    }

    /**
     * Professeurs et administrateurs rédigent les quiz ; les joueurs se contentent de jouer.
     * Un professeur ne retouche que ses propres quiz ; un admin, tous.
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        $user = $token->getUser();
        $owner = $subject->getOwner();

        return $user instanceof User
            && $this->accessDecisionManager->decide($token, ['ROLE_TEACHER'])
            && null !== $owner
            && $owner->getId() === $user->getId();
    }
}
