<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'security_login')]
    public function login(): Response
    {
        // Zalogowany użytkownik nie widzi ponownie strony logowania.
        if ($this->isGranted('ROLE_USER')) {
            return $this->redirectToRoute('place_index');
        }

        return $this->render('security/login.html.twig');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new \LogicException('Wylogowanie obsługuje firewall (security.yaml).');
    }
}
