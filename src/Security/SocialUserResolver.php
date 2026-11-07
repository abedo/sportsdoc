<?php

namespace App\Security;

use App\Entity\User;
use App\Entity\UserIdentity;
use App\Repository\UserIdentityRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

/**
 * Wybiera (lub tworzy) osobę po danych z Google albo Facebooka. Rozpoznajemy ją po stałym identyfikatorze
 * u dostawcy (UserIdentity), nie po e-mailu.
 *
 * Google: jeśli nie znamy jeszcze identyfikatora, dopinamy go do osoby z tym samym zweryfikowanym e-mailem
 * (np. dodanej wcześniej ręcznie). Facebook: nigdy nie łączymy po e-mailu, bo to ryzyko przejęcia konta.
 */
final readonly class SocialUserResolver
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $users,
        private UserIdentityRepository $identities,
    ) {
    }

    /** @param array<string, mixed> $data dane osoby od dostawcy (id lub sub, name, email) */
    public function resolve(string $provider, array $data): User
    {
        $subject = (string) ($data['id'] ?? $data['sub'] ?? '');
        if ('' === $subject) {
            throw new CustomUserMessageAuthenticationException('Dostawca logowania nie zwrócił identyfikatora konta.');
        }
        $email = $this->verifiedEmail($provider, $data);
        $name = trim((string) ($data['name'] ?? '')) ?: 'Uczestnik';
        $identity = $this->identities->findOneFor($provider, $subject);

        if (null !== $identity) {
            return $identity->getUser();
        }

        $existing = null !== $email ? $this->users->findOneBy(['email' => $email]) : null;
        // Przejmujemy istniejącą osobę tylko przez Google i tylko jeśli nie ma jeszcze innego konta Google.
        $user = 'google' === $provider && null !== $existing && !$existing->hasProvider('google') ? $existing : null;
        if (null === $user) {
            // Zajęty e-mail zostaje przy istniejącej osobie (unikalność); nowa osoba dostaje pusty.
            $user = (new User())->setFullname($name)->setEmail(null === $existing ? $email : null);
            $this->em->persist($user);
        }
        $this->em->persist(new UserIdentity($provider, $subject, $user));
        $this->em->flush();
        $this->em->refresh($user);

        return $user;
    }

    /** E-mail używamy tylko wtedy, gdy dostawca ręczy za jego prawdziwość (Google: email_verified). */
    private function verifiedEmail(string $provider, array $data): ?string
    {
        $email = trim((string) ($data['email'] ?? ''));
        if ('' === $email) {
            return null;
        }
        if ('google' === $provider && true !== filter_var($data['email_verified'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        return mb_strtolower($email);
    }
}
