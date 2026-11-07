<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\User;
use App\Repository\AttendanceRepository;
use App\Repository\PaymentRepository;
use App\Service\BalanceCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** „Moje rozliczenie”: gry, w których byłem, moje wpłaty i zgłoszenie nowej wpłaty. */
final class MeController extends AbstractController
{
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function index(BalanceCalculator $balanceCalculator, AttendanceRepository $attendances, PaymentRepository $payments): Response
    {
        $me = $this->currentUser();

        return $this->render('me/index.html.twig', [
            'balance' => $balanceCalculator->forUser($me),
            'attended' => $attendances->findBy(['user' => $me, 'present' => true], ['id' => 'DESC']),
            'payments' => $payments->findBy(['user' => $me], ['date' => 'DESC', 'id' => 'DESC']),
        ]);
    }

    /** Zgłoszenie wpłaty: czeka na potwierdzenie organizatora i do tego czasu nie zmienia salda. */
    #[Route('/me/payment', name: 'me_payment', methods: ['POST'])]
    public function reportPayment(Request $request, EntityManagerInterface $entityManager): Response
    {
        $me = $this->currentUser();
        if (!$this->isCsrfTokenValid('me-payment', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Nieprawidłowy token formularza. Odśwież stronę i spróbuj ponownie.');

            return $this->redirectToRoute('me');
        }

        $amount = filter_var($request->request->all()['amount'] ?? null, FILTER_VALIDATE_INT);
        if (false === $amount || $amount < 1 || $amount > 100000) {
            $this->addFlash('danger', 'Podaj kwotę wpłaty (liczba całkowita większa od zera).');

            return $this->redirectToRoute('me');
        }

        $payment = (new Payment())->setUser($me)->setAmount($amount)->setConfirmed(false);
        $entityManager->persist($payment);
        $entityManager->flush();
        $this->addFlash('success', 'Wpłata zgłoszona. Saldo zmieni się po potwierdzeniu przez organizatora.');

        return $this->redirectToRoute('me');
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
