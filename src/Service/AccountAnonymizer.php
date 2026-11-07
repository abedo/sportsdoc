<?php

namespace App\Service;

use App\Entity\PlaceMember;
use App\Entity\User;
use App\Entity\UserIdentity;
use App\Repository\PlaceMemberRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Usuwanie konta na prośbę osoby. Dane osobowe znikają (imię, e-mail, powiązanie z Google/Facebookiem, członkostwa),
 * a obecności i wpłaty zostają bez nazwiska, bo organizatorzy potrzebują ich do rozliczeń.
 */
final readonly class AccountAnonymizer
{
    public const ANONYMOUS_NAME = 'Usunięty użytkownik';

    public function __construct(
        private EntityManagerInterface $em,
        private PlaceMemberRepository $members,
    ) {
    }

    /** Miejsca, w których osoba jest jedynym organizatorem, a są w nich inni uczestnicy: najpierw trzeba kogoś mianować. */
    public function blockingPlaces(User $user): array
    {
        $blocking = [];
        foreach ($this->members->managedPlacesOf($user) as $place) {
            if ($this->members->organizerCount($place) < 2 && \count($this->members->membersOf($place)) > 1) {
                $blocking[] = $place;
            }
        }

        return $blocking;
    }

    public function anonymize(User $user): void
    {
        $this->em->createQueryBuilder()->delete(PlaceMember::class, 'm')->where('m.user = :u')->setParameter('u', $user)->getQuery()->execute();
        $this->em->createQueryBuilder()->delete(UserIdentity::class, 'i')->where('i.user = :u')->setParameter('u', $user)->getQuery()->execute();
        $user->setFullname(self::ANONYMOUS_NAME)->setEmail(null)->setRoles([]);
        $this->em->flush();
    }
}
