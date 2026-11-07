<?php

namespace App\Controller\Dev;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\GoogleAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

/**
 * Szybkie logowanie na dowolną osobę do oglądania aplikacji lokalnie bez Google.
 * Klasa istnieje WYŁĄCZNIE w środowisku dev (#[When]): w prod i test nie ma ani usługi, ani trasy.
 */
#[When(env: 'dev')]
final class DevLoginController extends AbstractController
{
    #[Route('/dev/login', name: 'dev_login', methods: ['GET'])]
    public function list(UserRepository $users): Response
    {
        return $this->render('dev/login.html.twig', ['users' => $users->findBy([], ['fullname' => 'ASC'])]);
    }

    #[Route('/dev/login/{id}', name: 'dev_login_as', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function loginAs(User $user, UserAuthenticatorInterface $userAuthenticator, GoogleAuthenticator $authenticator, \Symfony\Component\HttpFoundation\Request $request): Response
    {
        return $userAuthenticator->authenticateUser($user, $authenticator, $request) ?? $this->redirectToRoute('place_index');
    }
}
