<?php

namespace App\Security;

use App\Entity\User;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
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

/** Logowanie przez Google i Facebooka; dobór osoby robi SocialUserResolver. */
final class SocialAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    public const PROVIDERS = ['google', 'facebook'];
    public const LINK_SESSION_KEY = 'oauth_link_user_id';

    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly SocialUserResolver $resolver,
        private readonly RouterInterface $router,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return null !== $this->providerFor($request) && $request->isMethod('GET');
    }

    public function authenticate(Request $request): Passport
    {
        $provider = (string) $this->providerFor($request);
        $client = $this->clientRegistry->getClient($provider);
        $accessToken = $this->fetchAccessToken($client);
        $linkUserId = $request->getSession()->remove(self::LINK_SESSION_KEY);

        return new SelfValidatingPassport(new UserBadge($accessToken->getToken(), function () use ($provider, $client, $accessToken, $linkUserId): User {
            return $this->resolver->resolve($provider, $client->fetchUserFromToken($accessToken)->toArray(), null === $linkUserId ? null : (int) $linkUserId);
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->router->generate('home'));
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

    private function providerFor(Request $request): ?string
    {
        $route = (string) $request->attributes->get('_route');
        foreach (self::PROVIDERS as $provider) {
            if ('connect_'.$provider.'_check' === $route) {
                return $provider;
            }
        }

        return null;
    }
}
