<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GoogleController extends AbstractController
{
    /** Start logowania: przekierowanie do Google. */
    #[Route('/connect/google', name: 'connect_google')]
    public function connect(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry->getClient('google')->redirect(['openid', 'email', 'profile']);
    }

    /**
     * Google wraca tutaj po zalogowaniu. Żądanie przechwytuje GoogleAuthenticator,
     * więc ta akcja wykona się tylko, gdy authenticator nie zadziała.
     */
    #[Route('/connect/google/check', name: 'connect_google_check', methods: ['GET'])]
    public function check(): Response
    {
        return $this->redirectToRoute('security_login');
    }
}
