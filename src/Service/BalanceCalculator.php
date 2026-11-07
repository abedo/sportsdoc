<?php

namespace App\Service;

use App\Entity\Place;
use App\Entity\User;
use App\Repository\AttendanceRepository;
use App\Repository\PaymentRepository;
use App\Repository\PlaceMemberRepository;

/** Rozliczenie osoby w miejscu: koszt gier, w których była, minus potwierdzone wpłaty. Każde miejsce rozlicza się osobno. */
final readonly class BalanceCalculator
{
    public function __construct(
        private AttendanceRepository $attendances,
        private PaymentRepository $payments,
        private PlaceMemberRepository $members,
    ) {
    }

    /**
     * @return array{games: int, owed: int, paid: int, pending: int, balance: int}
     *                                                                            balance < 0: do dopłaty, > 0: nadpłata. Wpłaty niepotwierdzone nie liczą się do salda.
     */
    public function forUserInPlace(User $user, Place $place): array
    {
        $attended = $this->attendances->attendedTotals($user, $place);
        $paid = $this->payments->totalPaid($user, $place);

        return [
            'games' => $attended['games'],
            'owed' => $attended['cost'],
            'paid' => $paid,
            'pending' => $this->payments->totalPending($user, $place),
            'balance' => $paid - $attended['cost'],
        ];
    }

    /**
     * Rozliczenia osoby w każdym jej miejscu.
     *
     * @return list<array{place: Place, balance: array{games: int, owed: int, paid: int, pending: int, balance: int}}>
     */
    public function forUser(User $user): array
    {
        return array_map(
            fn (Place $place): array => ['place' => $place, 'balance' => $this->forUserInPlace($user, $place)],
            $this->members->placesOf($user),
        );
    }
}
