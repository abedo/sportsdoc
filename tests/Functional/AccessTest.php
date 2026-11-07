<?php

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;

final class AccessTest extends DatabaseWebTestCase
{
    /** @return iterable<string, array{string}> */
    public static function protectedUrls(): iterable
    {
        foreach (['/', '/place/', '/place/search', '/game/', '/me', '/welcome', '/payment/', '/place/new', '/game/new'] as $url) {
            yield $url => [$url];
        }
    }

    #[DataProvider('protectedUrls')]
    public function testAnonymousIsSentToLogin(string $url): void
    {
        $this->client->request('GET', $url);
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testHelpPageIsPublic(): void
    {
        $this->client->request('GET', '/help');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Organizator');
        self::assertSelectorTextContains('body', 'Uczestnik');
    }

    public function testOnlyOrganizersCanAddPlaces(): void
    {
        $this->loginAs($this->makeUser('jan@example.com', 'Jan Kowalski'));
        $this->client->request('GET', '/place/new');
        self::assertResponseStatusCodeSame(403);

        $this->loginAs($this->makeUser('org@example.com', 'Organizator', organizer: true));
        $this->client->request('GET', '/place/new');
        self::assertResponseIsSuccessful();
    }

    public function testPersonWithoutManagedPlacesHasNoPayments(): void
    {
        $this->loginAs($this->makeUser('jan@example.com', 'Jan Kowalski'));
        foreach (['/payment/', '/payment/new'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(403, $url);
        }
    }

    public function testParticipantCannotManageHisPlace(): void
    {
        $place = $this->makePlace();
        $game = $this->makeGame($place);
        $jan = $this->makeUser('jan@example.com', 'Jan Kowalski');
        $this->join($place, $jan);
        $this->loginAs($jan);

        foreach (['/game/'.$game->getId().'/edit', '/game/new?place='.$place->getId(), '/place/'.$place->getId().'/edit', '/place/'.$place->getId().'/members'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(403, $url);
        }
        $this->client->request('POST', '/game/'.$game->getId().'/attendance', ['present' => ['1']]);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/payment/1/confirm');
        self::assertResponseStatusCodeSame(404, 'płatność nie istnieje');
    }

    public function testNonMemberCannotSeeOrUseOtherPlaces(): void
    {
        $place = $this->makePlace();
        $game = $this->makeGame($place);
        $this->loginAs($this->makeUser('obcy@example.com', 'Obcy'));

        $this->client->request('GET', '/game/'.$game->getId());
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/game/'.$game->getId().'/me', ['present' => '1']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/place/'.$place->getId());
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/place/');
        self::assertSelectorNotExists('.swiper-slide');
        $this->client->request('GET', '/game/');
        self::assertSelectorNotExists('table');
    }

    public function testOrganizerOfOnePlaceCannotManageAnother(): void
    {
        $mine = $this->makePlace('Moje');
        $other = $this->makePlace('Cudze');
        $otherGame = $this->makeGame($other);
        $org = $this->makeUser('org@example.com', 'Organizator', organizer: true);
        $this->join($mine, $org, organizer: true);
        $this->loginAs($org);

        foreach (['/game/'.$otherGame->getId().'/edit', '/place/'.$other->getId().'/edit', '/place/'.$other->getId().'/members', '/game/new?place='.$other->getId()] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(403, $url);
        }
        $this->client->request('DELETE', '/place/'.$other->getId());
        self::assertResponseStatusCodeSame(403);
    }

    public function testMemberSeesPlacesGamesAndOwnSettlement(): void
    {
        $place = $this->makePlace();
        $game = $this->makeGame($place);
        $jan = $this->makeUser('jan@example.com', 'Jan Kowalski');
        $this->join($place, $jan);
        $this->loginAs($jan);

        foreach (['/place/', '/place/search', '/game/', '/game/'.$game->getId(), '/me'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
        }
    }

    public function testGarbageQueryParametersAreHandledGracefully(): void
    {
        $this->loginAs($this->makeUser('org@example.com', 'Organizator', organizer: true));

        $this->client->request('GET', '/place/?place=abc');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/place/?place[]=1');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/place/search?q[]=1');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/game/new?place=abc');
        self::assertResponseRedirects('/place/');
    }
}
