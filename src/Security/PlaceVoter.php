<?php

namespace App\Security;

use App\Entity\Game;
use App\Entity\Payment;
use App\Entity\Place;
use App\Entity\User;
use App\Repository\PlaceMemberRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Uprawnienia per miejsce.
 * PLACE_VIEW: członek miejsca. PLACE_MANAGE: organizator tego miejsca. Administrator serwisu ma jedno i drugie.
 * Podmiotem może być miejsce, gra albo wpłata (uprawnienie wynika z ich miejsca).
 */
final class PlaceVoter extends Voter
{
    public const VIEW = 'PLACE_VIEW';
    public const MANAGE = 'PLACE_MANAGE';

    public function __construct(private readonly PlaceMemberRepository $members)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::MANAGE], true)
            && ($subject instanceof Place || $subject instanceof Game || $subject instanceof Payment);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        $place = $subject instanceof Place ? $subject : $subject->getPlace();
        if (!$place instanceof Place) {
            return false;
        }
        $member = $this->members->findOneFor($place, $user);

        return null !== $member && (self::VIEW === $attribute || $member->isOrganizer());
    }
}
