<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\AttendanceRepository;
use App\Repository\PaymentRepository;

/** Rozliczenie osoby: koszt gier, w których była, minus potwierdzone wpłaty. */
final readonly class BalanceCalculator
{
    public function __construct(
        private AttendanceRepository $attendances,
        private PaymentRepository $payments,
    ) {
    }

    /**
     * @return array{games: int, owed: int, paid: int, pending: int, balance: int}
     *                                                                            balance < 0: do dopłaty, > 0: nadpłata. Wpłaty niepotwierdzone nie liczą się do salda.
     */
    public function forUser(User $user): array
    {
        $attended = $this->attendances->attendedTotals($user);
        $paid = $this->payments->totalPaid($user);

        return [
            'games' => $attended['games'],
            'owed' => $attended['cost'],
            'paid' => $paid,
            'pending' => $this->payments->totalPending($user),
            'balance' => $paid - $attended['cost'],
        ];
    }
}
