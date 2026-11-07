<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class GoogleAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly RouterInterface $router,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return 'connect_google_check' === $request->attributes->get('_route') && $request->isMethod('GET');
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(new UserBadge($accessToken->getToken(), function () use ($accessToken, $client): User {
            /** @var GoogleUser $googleUser */
            $googleUser = $client->fetchUserFromToken($accessToken);
            $email = (string) $googleUser->getEmail();

            $user = $this->users->findOneBy(['email' => $email]);
            if (null === $user) {
                $user = (new User())
                    ->setEmail($email)
                    ->setFullname((string) $googleUser->getName());
                $this->em->persist($user);
                $this->em->flush();
            }

            return $user;
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->router->generate('place_index'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new Response(strtr($exception->getMessageKey(), $exception->getMessageData()), Response::HTTP_FORBIDDEN);
    }

    /** Niezalogowany użytkownik na chronionej stronie trafia na ekran logowania. */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->router->generate('security_login'));
    }
}
