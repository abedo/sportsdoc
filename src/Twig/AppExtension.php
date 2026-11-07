<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\PaymentRepository;
use App\Service\ManagedPlaces;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly ManagedPlaces $managedPlaces,
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            // Czy osoba zarządza jakimkolwiek miejscem (menu „Płatności”).
            new TwigFunction('manages_any_place', fn (): bool => [] !== $this->managed()),
            // Liczba wpłat czekających na potwierdzenie w miejscach osoby (plakietka w menu).
            new TwigFunction('pending_payments_count', fn (): int => $this->payments->pendingCount($this->managed())),
        ];
    }

    /** @return list<\App\Entity\Place> */
    private function managed(): array
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $this->managedPlaces->forUser($user) : [];
    }
}
