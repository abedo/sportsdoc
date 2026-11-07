<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\User;
use App\Form\PaymentType;
use App\Repository\PaymentRepository;
use App\Repository\PlaceMemberRepository;
use App\Security\PlaceVoter;
use App\Service\ManagedPlaces;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Płatności zarządza organizator miejsca (swoimi miejscami). Osoby zgłaszają własne wpłaty w „Moim rozliczeniu” (MeController).
 */
#[Route('/payment')]
final class PaymentController extends AbstractController
{
    public function __construct(
        private readonly ManagedPlaces $managedPlaces,
        private readonly PlaceMemberRepository $members,
    ) {
    }

    #[Route('/', name: 'payment_index', methods: ['GET'])]
    public function index(PaymentRepository $paymentRepository): Response
    {
        $places = $this->managed();
        if ([] === $places) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('payment/index.html.twig', ['payments' => $paymentRepository->latestForPlaces($places)]);
    }

    #[Route('/new', name: 'payment_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $places = $this->managed();
        if ([] === $places) {
            throw $this->createAccessDeniedException();
        }

        $payment = new Payment();
        $form = $this->paymentForm($payment, $places);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->userBelongsToPlace($form, $payment)) {
            $entityManager->persist($payment);
            $entityManager->flush();

            return $this->redirectToRoute('payment_index');
        }

        return $this->render('payment/new.html.twig', ['payment' => $payment, 'form' => $form]);
    }

    #[Route('/{id}', name: 'payment_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(PlaceVoter::MANAGE, subject: 'payment')]
    public function show(Payment $payment): Response
    {
        return $this->render('payment/show.html.twig', ['payment' => $payment]);
    }

    #[Route('/{id}/edit', name: 'payment_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(PlaceVoter::MANAGE, subject: 'payment')]
    public function edit(Request $request, Payment $payment, EntityManagerInterface $entityManager): Response
    {
        $form = $this->paymentForm($payment, $this->managed());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->userBelongsToPlace($form, $payment)) {
            $entityManager->flush();

            return $this->redirectToRoute('payment_index');
        }

        return $this->render('payment/edit.html.twig', ['payment' => $payment, 'form' => $form]);
    }

    /** Organizator potwierdza, że wpłata faktycznie do niego dotarła. */
    #[Route('/{id}/confirm', name: 'payment_confirm', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(PlaceVoter::MANAGE, subject: 'payment')]
    public function confirm(Request $request, Payment $payment, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('confirm'.$payment->getId(), (string) $request->request->get('_token'))) {
            $payment->setConfirmed(true);
            $entityManager->flush();
            $this->addFlash('success', 'Wpłata potwierdzona.');
        }

        return $this->redirectToRoute('payment_index');
    }

    #[Route('/{id}', name: 'payment_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted(PlaceVoter::MANAGE, subject: 'payment')]
    public function delete(Request $request, Payment $payment, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$payment->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($payment);
            $entityManager->flush();
        }

        return $this->redirectToRoute('payment_index');
    }

    /** @return list<\App\Entity\Place> */
    private function managed(): array
    {
        $me = $this->getUser();

        return $me instanceof User ? $this->managedPlaces->forUser($me) : [];
    }

    /** @param list<\App\Entity\Place> $places */
    private function paymentForm(Payment $payment, array $places): FormInterface
    {
        return $this->createForm(PaymentType::class, $payment, [
            'places' => $places,
            'users' => $this->members->usersOfPlaces($places),
        ]);
    }

    /** Wpłatę można przypisać tylko osobie, która należy do wybranego miejsca. */
    private function userBelongsToPlace(FormInterface $form, Payment $payment): bool
    {
        $user = $payment->getUser();
        $place = $payment->getPlace();
        if ($user instanceof User && $place && $this->members->isMember($place, $user)) {
            return true;
        }
        $form->get('user')->addError(new FormError('Ta osoba nie należy do wybranego miejsca.'));

        return false;
    }
}
