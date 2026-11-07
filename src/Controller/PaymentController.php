<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Form\PaymentType;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Płatności zarządza organizator. Osoby zgłaszają własne wpłaty w „Moim rozliczeniu” (MeController). */
#[IsGranted('ROLE_ADMIN')]
#[Route('/payment')]
final class PaymentController extends AbstractController
{
    #[Route('/', name: 'payment_index', methods: ['GET'])]
    public function index(PaymentRepository $paymentRepository): Response
    {
        return $this->render('payment/index.html.twig', ['payments' => $paymentRepository->latest()]);
    }

    #[Route('/new', name: 'payment_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $payment = new Payment();
        $form = $this->createForm(PaymentType::class, $payment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($payment);
            $entityManager->flush();

            return $this->redirectToRoute('payment_index');
        }

        return $this->render('payment/new.html.twig', ['payment' => $payment, 'form' => $form]);
    }

    #[Route('/{id}', name: 'payment_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Payment $payment): Response
    {
        return $this->render('payment/show.html.twig', ['payment' => $payment]);
    }

    #[Route('/{id}/edit', name: 'payment_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Payment $payment, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PaymentType::class, $payment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('payment_index');
        }

        return $this->render('payment/edit.html.twig', ['payment' => $payment, 'form' => $form]);
    }

    /** Organizator potwierdza, że wpłata faktycznie do niego dotarła. */
    #[Route('/{id}/confirm', name: 'payment_confirm', methods: ['POST'], requirements: ['id' => '\d+'])]
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
    public function delete(Request $request, Payment $payment, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$payment->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($payment);
            $entityManager->flush();
        }

        return $this->redirectToRoute('payment_index');
    }
}
