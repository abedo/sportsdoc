<?php

namespace App\Tests\Functional;

use App\Entity\Attendance;
use App\Entity\Payment;
use App\Entity\Place;
use App\Entity\User;

/** Flow zwykłej osoby: własna obecność i zgłoszenie wpłaty (potwierdza organizator). */
final class ParticipantFlowTest extends DatabaseWebTestCase
{
    private User $jan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jan = $this->makeUser('jan@example.com', 'Jan Kowalski');
    }

    /** Miejsce, do którego Jan należy jako zwykły uczestnik. */
    private function janPlace(): \App\Entity\Place
    {
        $place = $this->makePlace();
        $this->join($place, $this->jan);

        return $place;
    }

    public function testRegularUserDoesNotSeeAdminButtons(): void
    {
        $place = $this->janPlace();
        $this->loginAs($this->jan);
        $this->client->request('GET', '/place/?place='.$place->getId());
        self::assertSelectorExists('.swiper-slide');
        self::assertSelectorNotExists('a[href^="/game/new"]');
        self::assertSelectorNotExists('a[href="/user/"]');
    }

    public function testUserMarksOwnAttendanceAndChangesIt(): void
    {
        $game = $this->makeGame($this->janPlace());
        $this->loginAs($this->jan);

        $crawler = $this->client->request('GET', '/game/'.$game->getId());
        self::assertSelectorTextContains('body', 'Nie zaznaczyłeś');
        $this->client->submit($crawler->selectButton('Byłem')->form());
        self::assertResponseRedirects('/game/'.$game->getId());
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Byłeś na tej grze');
        self::assertSelectorTextContains('body', 'Obecni (1)');

        $crawler = $this->client->request('GET', '/game/'.$game->getId());
        $this->client->submit($crawler->selectButton('Nie byłem')->form());
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Nie byłeś na tej grze');
        self::assertSelectorTextContains('body', 'Obecni (0)');

        $this->em->clear();
        self::assertCount(1, $this->em->getRepository(Attendance::class)->findAll(), 'zmiana nadpisuje wpis, nie dubluje');
    }

    public function testUserCannotMarkSomeoneElsesAttendance(): void
    {
        $game = $this->makeGame($this->janPlace());
        $ala = $this->makeUser('ala@example.com', 'Ala Nowak');
        $this->loginAs($this->jan);

        $crawler = $this->client->request('GET', '/game/'.$game->getId());
        $token = $crawler->filter('form[action$="/me"] input[name="_token"]')->attr('value');
        // Próba podszycia się pod Alę przez dodatkowe parametry: ignorowane, zapis dotyczy zalogowanego.
        $this->client->request('POST', '/game/'.$game->getId().'/me', ['_token' => $token, 'present' => '1', 'user' => $ala->getId(), 'user_id' => $ala->getId()]);

        $this->em->clear();
        $attendances = $this->em->getRepository(Attendance::class)->findAll();
        self::assertCount(1, $attendances);
        self::assertSame($this->jan->getId(), $attendances[0]->getUser()->getId());
    }

    public function testInvalidCsrfOnOwnAttendanceIsRejected(): void
    {
        $game = $this->makeGame($this->janPlace());
        $this->loginAs($this->jan);
        $this->client->request('POST', '/game/'.$game->getId().'/me', ['_token' => 'zly', 'present' => '1']);
        self::assertResponseRedirects('/game/'.$game->getId());
        self::assertCount(0, $this->em->getRepository(Attendance::class)->findAll());
    }

    public function testReportedPaymentIsPendingUntilOrganizerConfirms(): void
    {
        $place = $this->janPlace();
        $game = $this->makeGame($place, cost: 30);
        $this->em->persist((new Attendance())->setGame($game)->setUser($this->jan)->setPresent(true));
        $this->em->flush();

        // Osoba zgłasza wpłatę 30
        $this->loginAs($this->jan);
        $crawler = $this->client->request('GET', '/me');
        self::assertSelectorTextContains('body', '-30');
        $this->client->submit($crawler->selectButton('Zgłoś wpłatę')->form(['amount' => 30]));
        self::assertResponseRedirects('/me');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'czeka');
        self::assertSelectorTextContains('body', 'do dopłaty');

        $payment = $this->em->getRepository(Payment::class)->findOneBy([]);
        self::assertFalse($payment->isConfirmed());
        self::assertSame($this->jan->getId(), $payment->getUser()->getId());

        // Organizator widzi plakietkę i potwierdza
        $org = $this->makeUser('org@example.com', 'Organizator', organizer: true);
        $this->join($this->em->find(Place::class, $place->getId()), $org, organizer: true);
        $this->loginAs($org);
        $crawler = $this->client->request('GET', '/payment/');
        self::assertSelectorExists('.navbar .badge.text-bg-warning');
        $this->client->submit($crawler->selectButton('Potwierdź')->form());
        self::assertResponseRedirects('/payment/');

        $this->em->clear();
        self::assertTrue($this->em->find(Payment::class, $payment->getId())->isConfirmed());

        // Teraz saldo jest rozliczone
        $this->loginAs($this->em->find(User::class, $this->jan->getId()));
        $this->client->request('GET', '/me');
        self::assertSelectorTextContains('body', 'rozliczone');
    }

    public function testInvalidPaymentAmountIsRejected(): void
    {
        $this->janPlace();
        $this->loginAs($this->jan);
        $crawler = $this->client->request('GET', '/me');
        $token = $crawler->filter('form[action$="/me/payment"] input[name="_token"]')->attr('value');
        foreach (['0', '-5', 'abc', ''] as $amount) {
            $this->client->request('POST', '/me/payment', ['_token' => $token, 'amount' => $amount]);
            self::assertResponseRedirects('/me');
        }
        self::assertCount(0, $this->em->getRepository(Payment::class)->findAll());
    }
}
