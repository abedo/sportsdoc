<?php

namespace App\Tests\Functional;

use App\Entity\Attendance;
use App\Entity\Payment;
use App\Entity\PlaceMember;
use App\Entity\User;
use App\Entity\UserIdentity;

/** Strony prawne i usuwanie konta (anonimizacja z zachowaniem rozliczeń). */
final class AccountDeletionTest extends DatabaseWebTestCase
{
    public function testLegalPagesArePublicAndShowContact(): void
    {
        foreach (['/privacy', '/data-deletion'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
            self::assertSelectorExists('a[href="mailto:sportsdoc@abedo.pl"]', $url);
        }
        self::assertSelectorTextContains('body', 'Usuń konto');
    }

    public function testLegalPagesOpenForPersonWithoutRole(): void
    {
        $this->loginAs($this->makeUser('nowy@example.com', 'Nowy', roleless: true));
        $this->client->request('GET', '/privacy');
        self::assertResponseIsSuccessful();
    }

    public function testDeletingAccountErasesPersonalDataButKeepsSettlements(): void
    {
        $place = $this->makePlace();
        $org = $this->makeUser('org@example.com', 'Organizator', organizer: true);
        $jan = $this->makeUser('jan@example.com', 'Jan Kowalski');
        $this->join($place, $org, organizer: true);
        $this->join($place, $jan);
        $game = $this->makeGame($place);
        $this->em->persist((new Attendance())->setGame($game)->setUser($jan)->setPresent(true));
        $this->em->persist(new UserIdentity('google', 'g-1', $jan));
        $this->em->persist((new Payment())->setUser($jan)->setPlace($place)->setAmount(30)->setConfirmed(true));
        $this->em->flush();
        $janId = $jan->getId();

        $this->loginAs($jan);
        $crawler = $this->client->request('GET', '/me');
        $form = $crawler->selectButton('Usuń moje konto')->form();
        $form['confirm']->tick();
        $this->client->submit($form);
        self::assertResponseRedirects('/login');

        $this->em->clear();
        $user = $this->em->find(User::class, $janId);
        self::assertSame('Usunięty użytkownik', $user->getFullname());
        self::assertNull($user->getEmail());
        self::assertSame([], $this->em->getRepository(UserIdentity::class)->findBy(['user' => $janId]));
        self::assertSame([], $this->em->getRepository(PlaceMember::class)->findBy(['user' => $janId]));
        self::assertCount(1, $this->em->getRepository(Attendance::class)->findAll(), 'obecność zostaje');
        self::assertCount(1, $this->em->getRepository(Payment::class)->findAll(), 'wpłata zostaje');

        $this->client->request('GET', '/me');
        self::assertResponseRedirects('http://localhost/login', null, 'sesja zakończona po usunięciu konta');
    }

    public function testDeletionRequiresConfirmationAndValidToken(): void
    {
        $jan = $this->makeUser('jan@example.com', 'Jan Kowalski');
        $this->loginAs($jan);
        $crawler = $this->client->request('GET', '/me');
        $token = $crawler->filter('form[action$="/me/delete"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/me/delete', ['_token' => $token]);
        $this->client->request('POST', '/me/delete', ['_token' => 'zly', 'confirm' => '1']);
        $this->em->clear();
        self::assertSame('Jan Kowalski', $this->em->find(User::class, $jan->getId())->getFullname());
    }

    public function testSoleOrganizerOfPlaceWithOthersCannotDeleteAccount(): void
    {
        $place = $this->makePlace('Orlik');
        $org = $this->makeUser('org@example.com', 'Organizator', organizer: true);
        $this->join($place, $org, organizer: true);
        $this->join($place, $this->makeUser('jan@example.com', 'Jan'));

        $this->loginAs($org);
        $crawler = $this->client->request('GET', '/me');
        $form = $crawler->selectButton('Usuń moje konto')->form();
        $form['confirm']->tick();
        $this->client->submit($form);
        self::assertResponseRedirects('/me');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Orlik');

        $this->em->clear();
        self::assertSame('Organizator', $this->em->find(User::class, $org->getId())->getFullname());
    }

    public function testSoleOrganizerAloneInPlaceCanDelete(): void
    {
        $place = $this->makePlace();
        $org = $this->makeUser('org@example.com', 'Organizator', organizer: true);
        $this->join($place, $org, organizer: true);

        $this->loginAs($org);
        $crawler = $this->client->request('GET', '/me');
        $form = $crawler->selectButton('Usuń moje konto')->form();
        $form['confirm']->tick();
        $this->client->submit($form);
        self::assertResponseRedirects('/login');
    }
}
