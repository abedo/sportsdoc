<?php

namespace App\Tests\Functional;

use App\Entity\Attendance;
use App\Entity\User;

/** Widok gry jako slider po wszystkich grach miejsca; widok miejsca pokazuje tylko 5 ostatnich. */
final class GameSliderTest extends DatabaseWebTestCase
{
    private User $jan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jan = $this->makeUser('jan@example.com', 'Jan Kowalski');
    }

    public function testPlaceCardShowsOnlyFiveLatestGames(): void
    {
        $place = $this->makePlace();
        $this->join($place, $this->jan);
        for ($day = 1; $day <= 8; ++$day) {
            $this->makeGame($place, date: sprintf('2026-10-%02d', $day));
        }
        $this->em->refresh($place); // kolekcja gier w pamięci nie zna gier dodanych bezpośrednio
        $this->loginAs($this->jan);

        $crawler = $this->client->request('GET', '/place/?place='.$place->getId());
        $tiles = $crawler->filter('.swiper-slide a.game-tile');
        self::assertCount(5, $tiles);
        self::assertStringContainsString('08.10.2026', $tiles->first()->text(), 'najnowsza gra pierwsza');
        self::assertStringContainsString('04.10.2026', $tiles->last()->text());
        self::assertSelectorTextContains('.swiper-slide', 'Wszystkie gry (8)');
    }

    public function testGameViewIsASliderOfAllGamesOfThePlaceNewestFirst(): void
    {
        $place = $this->makePlace();
        $this->join($place, $this->jan);
        $games = [];
        for ($day = 1; $day <= 8; ++$day) {
            $games[$day] = $this->makeGame($place, date: sprintf('2026-10-%02d', $day));
        }
        $other = $this->makePlace('Cudze');
        $this->makeGame($other, date: '2026-10-20');
        $this->loginAs($this->jan);

        // Wejście na środkową grę: wszystkie 8 gier jest na sliderze, startowy slajd to ta gra.
        $crawler = $this->client->request('GET', '/game/'.$games[3]->getId());
        self::assertResponseIsSuccessful();
        $slides = $crawler->filter('.games-swiper .swiper-slide');
        self::assertCount(8, $slides, 'wszystkie gry miejsca, bez gier z innych miejsc');
        self::assertSame((string) $games[8]->getId(), $slides->first()->attr('data-game-id'), 'najnowsza pierwsza');
        self::assertSame((string) $games[1]->getId(), $slides->last()->attr('data-game-id'), 'najstarsza ostatnia');
        self::assertSame('5', $crawler->filter('.games-swiper')->attr('data-initial'), 'gra 3 z 8 (od najnowszej) ma indeks 5');
        self::assertSelectorExists('.games-swiper .swiper-button-next');
    }

    public function testEverySlideHasItsOwnAttendanceAndPresentList(): void
    {
        $place = $this->makePlace();
        $ala = $this->makeUser('ala@example.com', 'Ala Nowak');
        $this->join($place, $this->jan);
        $this->join($place, $ala);
        $old = $this->makeGame($place, date: '2026-10-01');
        $new = $this->makeGame($place, date: '2026-10-08');
        $this->em->persist((new Attendance())->setGame($old)->setUser($this->jan)->setPresent(true));
        $this->em->persist((new Attendance())->setGame($old)->setUser($ala)->setPresent(true));
        $this->em->persist((new Attendance())->setGame($new)->setUser($this->jan)->setPresent(false));
        $this->em->flush();
        $this->loginAs($this->jan);

        $crawler = $this->client->request('GET', '/game/'.$new->getId());
        $slides = $crawler->filter('.games-swiper .swiper-slide');
        self::assertStringContainsString('Nie byłeś na tej grze', $slides->eq(0)->text());
        self::assertStringContainsString('Obecni (0)', $slides->eq(0)->text());
        self::assertStringContainsString('Byłeś na tej grze', $slides->eq(1)->text());
        self::assertStringContainsString('Obecni (2)', $slides->eq(1)->text());
        self::assertStringContainsString('Ala Nowak', $slides->eq(1)->text());
        self::assertCount(2, $crawler->filter('.games-swiper form[action$="/me"]'), 'osobny formularz „Byłem” na każdym slajdzie');
    }

    public function testMarkingAttendanceOnAnOlderSlideComesBackToThatGame(): void
    {
        $place = $this->makePlace();
        $this->join($place, $this->jan);
        $old = $this->makeGame($place, date: '2026-10-01');
        $this->makeGame($place, date: '2026-10-08');
        $this->loginAs($this->jan);

        $crawler = $this->client->request('GET', '/game/'.$old->getId());
        $form = $crawler->filter('form[action="/game/'.$old->getId().'/me"]')->selectButton('Byłem')->form();
        $this->client->submit($form);
        self::assertResponseRedirects('/game/'.$old->getId(), null, 'po zapisie wracamy na tę samą grę, nie na najnowszą');
        $this->client->followRedirect();
        self::assertSame('1', $this->client->getCrawler()->filter('.games-swiper')->attr('data-initial'));
        self::assertSelectorTextContains('.games-swiper', 'Byłeś na tej grze');
    }

    public function testSingleGameHasNoArrows(): void
    {
        $place = $this->makePlace();
        $this->join($place, $this->jan);
        $game = $this->makeGame($place);
        $this->loginAs($this->jan);
        $this->client->request('GET', '/game/'.$game->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.swiper-button-next');
    }
}
