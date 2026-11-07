<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\User;
use App\Repository\AttendanceRepository;
use App\Repository\PaymentRepository;
use App\Repository\PlaceMemberRepository;
use App\Service\AccountAnonymizer;
use App\Service\BalanceCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** „Moje rozliczenie”: saldo w każdym moim miejscu, gry, w których byłem, wpłaty i zgłoszenie nowej wpłaty. */
final class MeController extends AbstractController
{
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function index(BalanceCalculator $balanceCalculator, AttendanceRepository $attendances, PaymentRepository $payments): Response
    {
        $me = $this->currentUser();

        return $this->render('me/index.html.twig', [
            'balances' => $balanceCalculator->forUser($me),
            'attended' => $attendances->findBy(['user' => $me, 'present' => true], ['id' => 'DESC']),
            'payments' => $payments->findBy(['user' => $me], ['date' => 'DESC', 'id' => 'DESC']),
        ]);
    }

    /** Zgłoszenie wpłaty: czeka na potwierdzenie organizatora miejsca i do tego czasu nie zmienia salda. */
    #[Route('/me/payment', name: 'me_payment', methods: ['POST'])]
    public function reportPayment(Request $request, EntityManagerInterface $entityManager, PlaceMemberRepository $members): Response
    {
        $me = $this->currentUser();
        if (!$this->isCsrfTokenValid('me-payment', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Nieprawidłowy token formularza. Odśwież stronę i spróbuj ponownie.');

            return $this->redirectToRoute('me');
        }

        $all = $request->request->all();
        $placeId = filter_var($all['place'] ?? null, FILTER_VALIDATE_INT);
        $place = false === $placeId ? null : array_find($members->placesOf($me), static fn ($p): bool => $p->getId() === $placeId);
        if (null === $place) {
            $this->addFlash('danger', 'Wybierz miejsce, do którego należysz.');

            return $this->redirectToRoute('me');
        }

        $amount = filter_var($all['amount'] ?? null, FILTER_VALIDATE_INT);
        if (false === $amount || $amount < 1 || $amount > 100000) {
            $this->addFlash('danger', 'Podaj kwotę wpłaty (liczba całkowita większa od zera).');

            return $this->redirectToRoute('me');
        }

        $payment = (new Payment())->setUser($me)->setPlace($place)->setAmount($amount)->setConfirmed(false);
        $entityManager->persist($payment);
        $entityManager->flush();
        $this->addFlash('success', 'Wpłata zgłoszona. Saldo zmieni się po potwierdzeniu przez organizatora miejsca.');

        return $this->redirectToRoute('me');
    }

    /** Usunięcie konta: dane osobowe znikają, obecności i wpłaty zostają bez nazwiska (rozliczenia organizatorów). */
    #[Route('/me/delete', name: 'me_delete', methods: ['POST'])]
    public function deleteAccount(Request $request, AccountAnonymizer $anonymizer, Security $security): Response
    {
        $me = $this->currentUser();
        if (!$this->isCsrfTokenValid('me-delete', (string) $request->request->get('_token')) || '1' !== ($request->request->all()['confirm'] ?? null)) {
            $this->addFlash('danger', 'Zaznacz potwierdzenie, żeby usunąć konto.');

            return $this->redirectToRoute('me');
        }
        $blocking = $anonymizer->blockingPlaces($me);
        if ([] !== $blocking) {
            $this->addFlash('danger', sprintf('Jesteś jedynym organizatorem miejsc: %s. Najpierw mianuj tam innego organizatora albo usuń uczestników.', implode(', ', array_map(static fn ($p): string => (string) $p->getName(), $blocking))));

            return $this->redirectToRoute('me');
        }

        $anonymizer->anonymize($me);
        $security->logout(false);
        $this->addFlash('success', 'Konto usunięte. Dane osobowe zostały skasowane.');

        return $this->redirectToRoute('security_login');
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
