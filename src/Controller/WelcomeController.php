<?php

namespace App\Controller;

use App\Entity\User;
use App\Security\SocialAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Wybór roli po pierwszym logowaniu oraz krótka instrukcja. */
final class WelcomeController extends AbstractController
{
    /** Pierwsze logowanie: osoba bez roli wybiera „jestem organizatorem” albo „jestem uczestnikiem”. Rolę można później zmienić. */
    #[Route('/welcome', name: 'choose_role', methods: ['GET', 'POST'])]
    public function chooseRole(Request $request, EntityManagerInterface $entityManager, Security $security): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {
            $role = $request->request->all()['role'] ?? null;
            if (!$this->isCsrfTokenValid('choose-role', (string) $request->request->get('_token')) || !\in_array($role, ['organizer', 'participant'], true)) {
                $this->addFlash('danger', 'Nie udało się zapisać wyboru. Spróbuj ponownie.');

                return $this->redirectToRoute('choose_role');
            }
            $user->chooseRole($role);
            $entityManager->flush();

            // Zmiana ról unieważnia dotychczasowy token (Symfony wylogowuje po zmianie ról), więc logujemy osobę ponownie w tej samej sesji.
            return $security->login($user, SocialAuthenticator::class, 'main') ?? $this->redirectToRoute('home');
        }

        return $this->render('welcome/choose_role.html.twig', ['current' => $user->getAppRole()]);
    }

    #[Route('/help', name: 'help', methods: ['GET'])]
    public function help(): Response
    {
        return $this->render('welcome/help.html.twig');
    }
}
