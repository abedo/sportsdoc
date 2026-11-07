<?php

namespace App\Twig;

use App\Repository\PaymentRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    public function __construct(private readonly PaymentRepository $payments)
    {
    }

    public function getFunctions(): array
    {
        return [
            // Liczba wpłat czekających na potwierdzenie (plakietka w menu admina).
            new TwigFunction('pending_payments_count', $this->payments->pendingCount(...)),
        ];
    }
}
