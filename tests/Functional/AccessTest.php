<?php

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;

final class AccessTest extends DatabaseWebTestCase
{
    /** @return iterable<string, array{string}> */
    public static function protectedUrls(): iterable
    {
        foreach (['/', '/place/', '/game/', '/me', '/user/', '/payment/', '/place/new', '/game/new'] as $url) {
            yield $url => [$url];
        }
    }

    #[DataProvider('protectedUrls')]
    public function testAnonymousIsSentToLogin(string $url): void
    {
        $this->client->request('GET', $url);
        self::assertResponseRedirects('http://localhost/login');
    }

    /** @return iterable<string, array{string}> */
    public static function adminOnlyUrls(): iterable
    {
        foreach (['/user/', '/user/new', '/payment/', '/payment/new', '/place/new', '/game/new'] as $url) {
            yield $url => [$url];
        }
    }

    #[DataProvider('adminOnlyUrls')]
    public function testRegularUserCannotOpenAdminPages(string $url): void
    {
        $this->loginAs($this->makeUser('jan@example.com', 'Jan Kowalski'));
        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(403);
    }

    public function testRegularUserCannotEditGameOrSaveEveryonesAttendance(): void
    {
        $game = $this->makeGame($this->makePlace());
        $this->loginAs($this->makeUser('jan@example.com', 'Jan Kowalski'));

        $this->client->request('GET', '/game/'.$game->getId().'/edit');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/game/'.$game->getId().'/attendance', ['present' => ['1']]);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/payment/1/confirm');
        self::assertResponseStatusCodeSame(403);
    }

    public function testRegularUserSeesPlacesGamesAndOwnSettlement(): void
    {
        $game = $this->makeGame($this->makePlace());
        $this->loginAs($this->makeUser('jan@example.com', 'Jan Kowalski'));

        foreach (['/place/', '/game/', '/game/'.$game->getId(), '/me'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
        }
    }

    public function testAdminCanOpenEverything(): void
    {
        $this->loginAs($this->makeUser('org@example.com', 'Organizator', admin: true));
        foreach (['/place/', '/game/', '/me', '/user/', '/user/new', '/payment/', '/payment/new', '/place/new'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
        }
    }

    public function testGarbageQueryParametersAreHandledGracefully(): void
    {
        $this->loginAs($this->makeUser('org@example.com', 'Organizator', admin: true));

        $this->client->request('GET', '/place/?place=abc');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/place/?place[]=1');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/game/new?place=abc');
        self::assertResponseRedirects('/place/');
    }
}
