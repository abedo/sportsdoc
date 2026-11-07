<?php

namespace App\Tests\Functional;

use App\Entity\Attendance;
use App\Entity\Game;
use App\Entity\Place;
use App\Entity\PlaceMember;
use App\Entity\User;

/** Flow organizatora: miejsce -> „Dodaj grę” (data) -> edycja gry (koszt) -> lista obecności. */
final class OrganizerFlowTest extends DatabaseWebTestCase
{
    private User $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = $this->makeUser('org@example.com', 'Organizator', organizer: true);
        $this->loginAs($this->org);
    }

    /** Miejsce, w którym zalogowana osoba jest organizatorem. */
    private function myPlace(string $name = 'Orlik'): Place
    {
        $place = $this->makePlace($name);
        $this->join($place, $this->org, organizer: true);

        return $place;
    }

    public function testNewPlaceShowsUpAsACardAndRedirectsToIt(): void
    {
        $crawler = $this->client->request('GET', '/place/new');
        $this->client->submit($crawler->selectButton('Zapisz')->form([
            'place[name]' => 'Orlik',
            'place[address]' => 'Sportowa 1',
            'place[city]' => 'Poznań',
            'place[zip]' => '60-001',
        ]));
        $place = $this->em->getRepository(Place::class)->findOneBy(['name' => 'Orlik']);
        self::assertNotNull($place);
        self::assertResponseRedirects('/place/?place='.$place->getId());
        self::assertTrue($this->em->getRepository(PlaceMember::class)->findOneBy(['place' => $place, 'user' => $this->org])->isOrganizer(), 'twórca miejsca zostaje jego organizatorem');

        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.swiper-slide', 'Orlik');
        self::assertSelectorExists('a[href="/game/new?place='.$place->getId().'"]');
    }

    public function testPlacesSwiperStartsOnRequestedPlace(): void
    {
        $this->myPlace('Alfa');
        $b = $this->myPlace('Beta');
        $this->myPlace('Gamma');

        $crawler = $this->client->request('GET', '/place/?place='.$b->getId());
        self::assertCount(3, $crawler->filter('.swiper-slide'));
        self::assertSame('1', $crawler->filter('.places-swiper')->attr('data-initial'), 'sortowanie po nazwie: Beta jest druga');
    }

    public function testAddGameFromPlaceWithDateOnlyThenEditCostAndMarkAttendance(): void
    {
        $place = $this->myPlace();
        $this->makeGame($place, cost: 25, date: '2026-10-01');
        $jan = $this->makeUser('jan@example.com', 'Jan Kowalski');
        $ala = $this->makeUser('ala@example.com', 'Ala Nowak');
        $this->join($place, $jan);
        $this->join($place, $ala);
        $this->makeUser('obcy@example.com', 'Obcy Spoza Miejsca');

        // „Dodaj grę” z karty miejsca: tylko data
        $crawler = $this->client->request('GET', '/game/new?place='.$place->getId());
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('input[name="game_create[cost]"]'));
        $this->client->submit($crawler->selectButton('Dodaj grę')->form(['game_create[createdAt]' => '2026-11-10']));

        $game = $this->em->getRepository(Game::class)->findOneBy(['createdAt' => new \DateTime('2026-11-10')]);
        self::assertNotNull($game);
        self::assertSame(25, $game->getCost(), 'koszt podpowiedziany z poprzedniej gry w tym miejscu');
        self::assertSame($place->getId(), $game->getPlace()->getId());
        self::assertResponseRedirects('/game/'.$game->getId().'/edit');

        // Edycja gry: koszt
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->selectButton('Zapisz zmiany')->form(['game[cost]' => 40]));
        self::assertResponseRedirects('/game/'.$game->getId().'/edit');
        $this->em->clear();
        self::assertSame(40, $this->em->find(Game::class, $game->getId())->getCost());

        // Lista obecności: zaznaczamy tylko Jana
        $crawler = $this->client->request('GET', '/game/'.$game->getId().'/edit');
        self::assertCount(3, $crawler->filter('input[name="present[]"]'), 'organizator i dwie osoby z miejsca, bez osób spoza miejsca');
        $form = $crawler->selectButton('Zapisz obecność')->form();
        $values = $form->getPhpValues();
        $values['present'] = [(string) $jan->getId()];
        $this->client->request('POST', $form->getUri(), $values);
        self::assertResponseRedirects('/game/'.$game->getId().'/edit');

        $this->em->clear();
        $repo = $this->em->getRepository(Attendance::class);
        self::assertTrue($repo->findOneBy(['user' => $jan->getId()])->isPresent());
        self::assertFalse($repo->findOneBy(['user' => $ala->getId()])->isPresent());

        $this->client->followRedirect();
        self::assertSelectorTextContains('body', '1 z 3');
    }

    public function testSavingAttendanceTwiceDoesNotDuplicate(): void
    {
        $place = $this->myPlace();
        $game = $this->makeGame($place);
        $jan = $this->makeUser('jan@example.com', 'Jan Kowalski');
        $ala = $this->makeUser('ala@example.com', 'Ala Nowak');
        $this->join($place, $jan);
        $this->join($place, $ala);

        foreach ([[$jan->getId()], [$ala->getId()]] as $ids) {
            $crawler = $this->client->request('GET', '/game/'.$game->getId().'/edit');
            $form = $crawler->selectButton('Zapisz obecność')->form();
            $values = $form->getPhpValues();
            $values['present'] = array_map('strval', $ids);
            $this->client->request('POST', $form->getUri(), $values);
        }

        $this->em->clear();
        $repo = $this->em->getRepository(Attendance::class);
        self::assertCount(3, $repo->findAll(), 'jeden wpis na osobę (z organizatorem), bez dubli');
        self::assertFalse($repo->findOneBy(['user' => $jan->getId()])->isPresent());
        self::assertTrue($repo->findOneBy(['user' => $ala->getId()])->isPresent());
    }

    public function testInvalidCsrfOnAttendanceDoesNotSave(): void
    {
        $game = $this->makeGame($this->myPlace());
        $this->client->request('POST', '/game/'.$game->getId().'/attendance', ['_token' => 'zly', 'present' => ['1']]);
        self::assertResponseRedirects('/game/'.$game->getId().'/edit');
        self::assertCount(0, $this->em->getRepository(Attendance::class)->findAll());
    }

    public function testPlaceWithGamesCannotBeDeleted(): void
    {
        $place = $this->myPlace();
        $this->makeGame($place);

        $crawler = $this->client->request('GET', '/place/?place='.$place->getId());
        $this->client->submit($crawler->selectButton('Usuń miejsce')->form());
        self::assertResponseRedirects();
        $this->em->clear();
        self::assertNotNull($this->em->find(Place::class, $place->getId()));
    }

    public function testDeleteGameRemovesItsAttendanceAndReturnsToPlace(): void
    {
        $place = $this->myPlace();
        $game = $this->makeGame($place);
        $jan = $this->makeUser('jan@example.com', 'Jan Kowalski');
        $this->join($place, $jan);
        $this->em->persist((new Attendance())->setGame($game)->setUser($jan)->setPresent(true));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/game/'.$game->getId().'/edit');
        $this->client->submit($crawler->selectButton('Usuń grę')->form());
        self::assertResponseRedirects('/place/?place='.$place->getId());
        $this->em->clear();
        self::assertNull($this->em->find(Game::class, $game->getId()));
        self::assertCount(0, $this->em->getRepository(Attendance::class)->findAll());
    }

    public function testAdminCanGrantOrganizerRoleInUserForm(): void
    {
        $this->loginAs($this->makeUser('admin@example.com', 'Admin', admin: true));
        $crawler = $this->client->request('GET', '/user/new');
        $this->client->submit($crawler->selectButton('Dodaj')->form([
            'user[email]' => 'nowy@example.com',
            'user[fullname]' => 'Nowy Organizator',
            'user[organizer]' => true,
        ]));
        self::assertResponseRedirects('/user/');
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'nowy@example.com']);
        self::assertContains('ROLE_ORGANIZER', $user->getRoles());
        self::assertNotContains('ROLE_ADMIN', $user->getRoles());
    }
}
