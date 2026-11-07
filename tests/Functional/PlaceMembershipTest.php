<?php

namespace App\Tests\Functional;

use App\Entity\Payment;
use App\Entity\PlaceMember;
use App\Entity\User;

/** Wyszukiwanie miejsc, dołączanie, opuszczanie i zarządzanie uczestnikami. */
final class PlaceMembershipTest extends DatabaseWebTestCase
{
    public function testSearchFindsPlacesByNameOrCityAndParticipantJoins(): void
    {
        $this->makePlace('Orlik Jeżyce');
        $this->makePlace('Hala Sportowa');
        $jan = $this->makeUser('jan@example.com', 'Jan Kowalski');
        $this->loginAs($jan);

        $crawler = $this->client->request('GET', '/place/search?q=orlik');
        self::assertCount(1, $crawler->filter('.list-group-item'));
        self::assertSelectorTextContains('.list-group-item', 'Orlik Jeżyce');

        $crawler = $this->client->request('GET', '/place/search?q=Poznań');
        self::assertCount(2, $crawler->filter('.list-group-item'), 'szukanie po mieście');

        $crawler = $this->client->request('GET', '/place/search?q=orlik');
        $this->client->submit($crawler->selectButton('Dołącz')->form());
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.swiper-slide', 'Orlik Jeżyce');

        $member = $this->em->getRepository(PlaceMember::class)->findOneBy(['user' => $jan->getId()]);
        self::assertNotNull($member);
        self::assertFalse($member->isOrganizer(), 'dołączający zostaje zwykłym uczestnikiem');

        $crawler = $this->client->request('GET', '/place/search?q=orlik');
        self::assertSelectorNotExists('button.btn-success', 'po dołączeniu nie ma już przycisku „Dołącz”');
    }

    public function testJoiningTwiceDoesNotDuplicateAndWildcardsAreLiteral(): void
    {
        $place = $this->makePlace('Orlik');
        $jan = $this->makeUser('jan@example.com', 'Jan Kowalski');
        $this->loginAs($jan);

        $crawler = $this->client->request('GET', '/place/search');
        $token = $crawler->filter('form[action$="/join"] input[name="_token"]')->attr('value');
        $this->client->request('POST', '/place/'.$place->getId().'/join', ['_token' => $token]);
        $this->client->request('POST', '/place/'.$place->getId().'/join', ['_token' => $token]);
        self::assertCount(1, $this->em->getRepository(PlaceMember::class)->findAll());

        $this->client->request('GET', '/place/search?q=%25');
        self::assertSelectorNotExists('.list-group-item', '% nie jest wzorcem, tylko znakiem');
    }

    public function testJoinWithBadCsrfIsRejected(): void
    {
        $place = $this->makePlace();
        $this->loginAs($this->makeUser('jan@example.com', 'Jan Kowalski'));
        $this->client->request('POST', '/place/'.$place->getId().'/join', ['_token' => 'zly']);
        self::assertCount(0, $this->em->getRepository(PlaceMember::class)->findAll());
    }

    public function testLeavingKeepsHistoryButLastOrganizerCannotLeave(): void
    {
        $place = $this->makePlace();
        $org = $this->makeUser('org@example.com', 'Organizator', organizer: true);
        $jan = $this->makeUser('jan@example.com', 'Jan Kowalski');
        $this->join($place, $org, organizer: true);
        $this->join($place, $jan);
        $game = $this->makeGame($place);
        $this->em->persist((new \App\Entity\Attendance())->setGame($game)->setUser($jan)->setPresent(true));
        $this->em->flush();

        $this->loginAs($org);
        $crawler = $this->client->request('GET', '/place/?place='.$place->getId());
        $this->client->submit($crawler->selectButton('Opuść miejsce')->form());
        self::assertNotNull($this->em->getRepository(PlaceMember::class)->findOneBy(['user' => $org->getId()]), 'jedyny organizator nie może odejść');

        $this->loginAs($jan);
        $crawler = $this->client->request('GET', '/place/?place='.$place->getId());
        $this->client->submit($crawler->selectButton('Opuść miejsce')->form());
        $this->em->clear();
        self::assertNull($this->em->getRepository(PlaceMember::class)->findOneBy(['user' => $jan->getId()]));
        self::assertCount(1, $this->em->getRepository(\App\Entity\Attendance::class)->findAll(), 'historia obecności zostaje');
    }

    public function testOrganizerManagesMembersButKeepsAtLeastOneOrganizer(): void
    {
        $place = $this->makePlace();
        $org = $this->makeUser('org@example.com', 'Organizator', organizer: true);
        $jan = $this->makeUser('jan@example.com', 'Jan Kowalski');
        $orgMember = $this->join($place, $org, organizer: true);
        $janMember = $this->join($place, $jan);
        $this->loginAs($org);

        $crawler = $this->client->request('GET', '/place/'.$place->getId().'/members');
        self::assertResponseIsSuccessful();

        // Nie można odebrać roli jedynemu organizatorowi ani go usunąć.
        $token = static fn (PlaceMember $m): string => $crawler->filter('form[action$="/members/'.$m->getId().'/remove"] input[name="_token"]')->attr('value');
        $this->client->request('POST', sprintf('/place/%d/members/%d/organizer', $place->getId(), $orgMember->getId()), ['_token' => $token($orgMember)]);
        $this->client->request('POST', sprintf('/place/%d/members/%d/remove', $place->getId(), $orgMember->getId()), ['_token' => $token($orgMember)]);
        $this->em->clear();
        self::assertTrue($this->em->find(PlaceMember::class, $orgMember->getId())?->isOrganizer());

        // Mianowanie Jana organizatorem, potem usunięcie go z miejsca.
        $this->client->request('POST', sprintf('/place/%d/members/%d/organizer', $place->getId(), $janMember->getId()), ['_token' => $token($janMember)]);
        $this->em->clear();
        self::assertTrue($this->em->find(PlaceMember::class, $janMember->getId())->isOrganizer());

        $this->client->request('POST', sprintf('/place/%d/members/%d/remove', $place->getId(), $janMember->getId()), ['_token' => $token($janMember)]);
        $this->em->clear();
        self::assertNull($this->em->find(PlaceMember::class, $janMember->getId()));
    }

    public function testMemberOfAnotherPlaceCannotBeManagedThroughMyPlaceUrl(): void
    {
        $mine = $this->makePlace('Moje');
        $other = $this->makePlace('Cudze');
        $org = $this->makeUser('org@example.com', 'Organizator', organizer: true);
        $jan = $this->makeUser('jan@example.com', 'Jan Kowalski');
        $this->join($mine, $org, organizer: true);
        $foreign = $this->join($other, $jan);
        $this->loginAs($org);

        // Token CSRF w tej aplikacji jest wspólny dla sesji, więc bierzemy go z własnej strony uczestników.
        $mineMember = $this->em->getRepository(PlaceMember::class)->findOneBy(['place' => $mine, 'user' => $org]);
        $crawler = $this->client->request('GET', '/place/'.$mine->getId().'/members');
        $token = $crawler->filter('form[action$="/members/'.$mineMember->getId().'/remove"] input[name="_token"]')->attr('value');
        $this->client->request('POST', sprintf('/place/%d/members/%d/remove', $mine->getId(), $foreign->getId()), ['_token' => $token]);
        self::assertResponseStatusCodeSame(404);
        self::assertNotNull($this->em->find(PlaceMember::class, $foreign->getId()));
    }

    public function testPaymentsAreSettledPerPlaceAndConfirmedOnlyByThatPlacesOrganizer(): void
    {
        $a = $this->makePlace('Alfa');
        $b = $this->makePlace('Beta');
        $orgA = $this->makeUser('a@example.com', 'Organizator A', organizer: true);
        $orgB = $this->makeUser('b@example.com', 'Organizator B', organizer: true);
        $jan = $this->makeUser('jan@example.com', 'Jan Kowalski');
        $this->join($a, $orgA, organizer: true);
        $this->join($b, $orgB, organizer: true);
        $this->join($a, $jan);
        $this->join($b, $jan);
        $gameA = $this->makeGame($a, cost: 30);
        $this->em->persist((new \App\Entity\Attendance())->setGame($gameA)->setUser($jan)->setPresent(true));
        $this->em->flush();

        // Jan zgłasza wpłatę 30 w miejscu Alfa.
        $this->loginAs($jan);
        $crawler = $this->client->request('GET', '/me');
        $this->client->submit($crawler->selectButton('Zgłoś wpłatę')->form(['place' => $a->getId(), 'amount' => 30]));
        $payment = $this->em->getRepository(Payment::class)->findOneBy([]);
        self::assertSame($a->getId(), $payment->getPlace()->getId());

        // Organizator Beta nie widzi i nie potwierdzi cudzej wpłaty.
        $this->loginAs($orgB);
        $this->client->request('POST', '/payment/'.$payment->getId().'/confirm');
        self::assertResponseStatusCodeSame(403);
        $crawler = $this->client->request('GET', '/payment/');
        self::assertSelectorTextNotContains('table', 'Jan Kowalski');

        // Organizator Alfa potwierdza.
        $this->loginAs($orgA);
        $crawler = $this->client->request('GET', '/payment/');
        self::assertSelectorTextContains('table', 'Jan Kowalski');
        $this->client->submit($crawler->selectButton('Potwierdź')->form());
        $this->em->clear();
        self::assertTrue($this->em->find(Payment::class, $payment->getId())->isConfirmed());

        // Saldo: Alfa rozliczone, Beta bez gier.
        $this->loginAs($this->em->find(User::class, $jan->getId()));
        $this->client->request('GET', '/me');
        self::assertSelectorTextContains('body', 'rozliczone');
    }

    public function testReportingPaymentForForeignPlaceIsRejected(): void
    {
        $place = $this->makePlace();
        $jan = $this->makeUser('jan@example.com', 'Jan Kowalski');
        $other = $this->makePlace('Cudze');
        $this->join($place, $jan);
        $this->loginAs($jan);

        $crawler = $this->client->request('GET', '/me');
        $token = $crawler->filter('form[action$="/me/payment"] input[name="_token"]')->attr('value');
        $this->client->request('POST', '/me/payment', ['_token' => $token, 'place' => $other->getId(), 'amount' => '30']);
        self::assertResponseRedirects('/me');
        self::assertCount(0, $this->em->getRepository(Payment::class)->findAll());
    }

    public function testOrganizerCannotAssignPaymentToPersonOutsideThePlace(): void
    {
        $place = $this->makePlace();
        $org = $this->makeUser('org@example.com', 'Organizator', organizer: true);
        $this->join($place, $org, organizer: true);
        $obcy = $this->makeUser('obcy@example.com', 'Obcy');
        $this->loginAs($org);

        $crawler = $this->client->request('GET', '/payment/new');
        self::assertCount(1, $crawler->filter('select[name="payment[user]"] option'), 'na liście tylko członkowie miejsca');
        $form = $crawler->selectButton('Dodaj')->form();
        $values = $form->getPhpValues();
        $values['payment']['user'] = (string) $obcy->getId();
        $values['payment']['amount'] = '20';
        $this->client->request('POST', $form->getUri(), $values);
        self::assertCount(0, $this->em->getRepository(Payment::class)->findAll());
    }
}
