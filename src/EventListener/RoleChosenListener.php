<?php

namespace App\EventListener;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\RouterInterface;

/** Zalogowana osoba, która nie wybrała jeszcze roli, trafia na stronę wyboru zamiast na inne ekrany. */
#[AsEventListener(event: RequestEvent::class, priority: 0)]
final readonly class RoleChosenListener
{
    private const ALLOWED_ROUTES = ['choose_role', 'help', 'app_logout', 'security_login', 'connect', 'connect_google_check', 'connect_facebook_check'];

    public function __construct(private Security $security, private RouterInterface $router)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $route = (string) $event->getRequest()->attributes->get('_route');
        if ('' === $route || str_starts_with($route, '_') || str_starts_with($route, 'dev_') || \in_array($route, self::ALLOWED_ROUTES, true)) {
            return;
        }
        $user = $this->security->getUser();
        if ($user instanceof User && null === $user->getAppRole()) {
            $event->setResponse(new RedirectResponse($this->router->generate('choose_role')));
        }
    }
}
