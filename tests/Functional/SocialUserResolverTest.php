<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Entity\UserIdentity;
use App\Security\SocialUserResolver;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/** Dobór osoby po logowaniu przez Google i Facebooka (bez kontaktu z dostawcami). */
final class SocialUserResolverTest extends DatabaseWebTestCase
{
    private SocialUserResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = static::getContainer()->get(SocialUserResolver::class);
    }

    public function testFirstGoogleLoginCreatesUserAndSecondOneReusesIt(): void
    {
        $data = ['sub' => 'g-1', 'name' => 'Jan Kowalski', 'email' => 'Jan@Example.com', 'email_verified' => true];
        $first = $this->resolver->resolve('google', $data);
        $again = $this->resolver->resolve('google', ['sub' => 'g-1', 'name' => 'Zmienione', 'email' => 'inny@example.com']);

        self::assertSame($first->getId(), $again->getId());
        self::assertSame('jan@example.com', $first->getEmail());
        self::assertSame('Jan Kowalski', $first->getFullname());
        self::assertCount(1, $this->em->getRepository(UserIdentity::class)->findAll());
        self::assertSame([], array_diff($first->getRoles(), ['ROLE_USER']), 'nowa osoba nie dostaje żadnych ról');
    }

    public function testGoogleAttachesToPersonAddedByAdminWithSameVerifiedEmail(): void
    {
        $added = $this->makeUser('ala@example.com', 'Ala (dodana ręcznie)');
        $user = $this->resolver->resolve('google', ['sub' => 'g-2', 'name' => 'Ala Nowak', 'email' => 'ala@example.com', 'email_verified' => true]);
        self::assertSame($added->getId(), $user->getId());
    }

    public function testUnverifiedGoogleEmailDoesNotTakeOverAnAccount(): void
    {
        $added = $this->makeUser('ala@example.com', 'Ala');
        $user = $this->resolver->resolve('google', ['sub' => 'g-3', 'name' => 'Ktoś', 'email' => 'ala@example.com', 'email_verified' => false]);
        self::assertNotSame($added->getId(), $user->getId());
        self::assertNull($user->getEmail());
    }

    public function testFacebookNeverMergesByEmailAndWorksWithoutEmail(): void
    {
        $existing = $this->makeUser('ala@example.com', 'Ala');
        $viaFacebook = $this->resolver->resolve('facebook', ['id' => 'fb-1', 'name' => 'Ala F', 'email' => 'ala@example.com']);
        self::assertNotSame($existing->getId(), $viaFacebook->getId());
        self::assertNull($viaFacebook->getEmail(), 'e-mail zajęty przez inną osobę zostaje przy niej');

        $noEmail = $this->resolver->resolve('facebook', ['id' => 'fb-2', 'name' => 'Bez Maila']);
        self::assertNull($noEmail->getEmail());
        self::assertNotNull($noEmail->getId());
        self::assertSame((string) $noEmail->getId(), $noEmail->getUserIdentifier());
    }

    public function testSameIdAtDifferentProvidersAreDifferentPeople(): void
    {
        $g = $this->resolver->resolve('google', ['sub' => '777', 'name' => 'G']);
        $f = $this->resolver->resolve('facebook', ['id' => '777', 'name' => 'F']);
        self::assertNotSame($g->getId(), $f->getId());
    }

    public function testLinkingAddsSecondProviderToTheLoggedInPerson(): void
    {
        $me = $this->resolver->resolve('google', ['sub' => 'g-9', 'name' => 'Ja']);
        $linked = $this->resolver->resolve('facebook', ['id' => 'fb-9', 'name' => 'Ja na FB'], $me->getId());
        self::assertSame($me->getId(), $linked->getId());
        self::assertTrue($me->hasProvider('facebook'));

        // Od teraz Facebook loguje na to samo konto.
        $login = $this->resolver->resolve('facebook', ['id' => 'fb-9', 'name' => 'Ja na FB']);
        self::assertSame($me->getId(), $login->getId());
        self::assertCount(1, $this->em->getRepository(User::class)->findAll());
    }

    public function testLinkingAnIdentityOwnedBySomeoneElseIsRefused(): void
    {
        $other = $this->resolver->resolve('facebook', ['id' => 'fb-5', 'name' => 'Inny']);
        $me = $this->resolver->resolve('google', ['sub' => 'g-5', 'name' => 'Ja']);

        $this->expectException(AuthenticationException::class);
        $this->resolver->resolve('facebook', ['id' => 'fb-5', 'name' => 'Inny'], $me->getId());
        self::assertNotSame($other->getId(), $me->getId());
    }

    public function testMissingProviderIdIsRefused(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->resolver->resolve('facebook', ['name' => 'Bez id']);
    }
}
