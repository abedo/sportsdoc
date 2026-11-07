<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Logowanie przez Google i Facebooka (callbacki przechwytuje SocialAuthenticator). */
final class SocialController extends AbstractController
{
    private const SCOPES = [
        'google' => ['openid', 'email', 'profile'],
        'facebook' => ['public_profile', 'email'],
    ];

    #[Route('/connect/{provider}', name: 'connect', requirements: ['provider' => 'google|facebook'], methods: ['GET'])]
    public function connect(string $provider, ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry->getClient($provider)->redirect(self::SCOPES[$provider], []);
    }

    #[Route('/connect/google/check', name: 'connect_google_check', methods: ['GET'])]
    #[Route('/connect/facebook/check', name: 'connect_facebook_check', methods: ['GET'])]
    public function check(): Response
    {
        // Żądanie przechwytuje SocialAuthenticator; tu trafiamy tylko, gdy nie zadziałał.
        return $this->redirectToRoute('security_login');
    }
}
