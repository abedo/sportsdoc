<?php

namespace App\Tests\Functional;

use App\Entity\User;

/** Pierwsze logowanie: wybór roli i domyślny widok po zalogowaniu. */
final class RoleChoiceTest extends DatabaseWebTestCase
{
    public function testPersonWithoutRoleIsSentToRoleChoiceEverywhere(): void
    {
        $this->loginAs($this->makeUser('nowy@example.com', 'Nowy', roleless: true));
        foreach (['/', '/place/', '/game/', '/me', '/payment/', '/place/search'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseRedirects('/welcome', null, $url);
        }
        $crawler = $this->client->request('GET', '/welcome');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('button[value="organizer"]');
        self::assertSelectorExists('button[value="participant"]');

        $this->client->request('GET', '/help');
        self::assertResponseIsSuccessful('instrukcja jest dostępna bez wyboru roli');
    }

    public function testChoosingOrganizerLeadsToPlaces(): void
    {
        $user = $this->makeUser('nowy@example.com', 'Nowy', roleless: true);
        $this->loginAs($user);
        $crawler = $this->client->request('GET', '/welcome');
        $this->client->submit($crawler->selectButton('Jestem organizatorem')->form());
        self::assertResponseRedirects('/');

        $this->client->followRedirect();
        self::assertResponseRedirects('/place/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.navbar', 'organizator');

        $this->em->clear();
        self::assertSame('organizer', $this->em->find(User::class, $user->getId())->getAppRole());
        $this->client->request('GET', '/place/new');
        self::assertResponseIsSuccessful('organizator może zakładać miejsca');
    }

    public function testChoosingParticipantLeadsToGamesAndCannotAddPlaces(): void
    {
        $this->loginAs($this->makeUser('nowy@example.com', 'Nowy', roleless: true));
        $crawler = $this->client->request('GET', '/welcome');
        $this->client->submit($crawler->selectButton('Jestem uczestnikiem')->form());

        $this->client->request('GET', '/');
        self::assertResponseRedirects('/game/');
        $this->client->request('GET', '/game/');
        self::assertSelectorTextContains('.navbar', 'uczestnik');
        $this->client->request('GET', '/place/new');
        self::assertResponseStatusCodeSame(403);
    }

    public function testChosenRoleSkipsTheChoiceOnNextLogin(): void
    {
        $this->loginAs($this->makeUser('jan@example.com', 'Jan'));
        $this->client->request('GET', '/');
        self::assertResponseRedirects('/game/');

        $this->loginAs($this->makeUser('org@example.com', 'Org', organizer: true));
        $this->client->request('GET', '/login');
        self::assertResponseRedirects('/');
        $this->client->request('GET', '/');
        self::assertResponseRedirects('/place/');
    }

    public function testRoleCanBeChangedAndGarbageIsRejected(): void
    {
        $user = $this->makeUser('jan@example.com', 'Jan');
        $this->loginAs($user);
        $crawler = $this->client->request('GET', '/welcome');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/welcome', ['_token' => $token, 'role' => 'ROLE_ADMIN']);
        self::assertResponseRedirects('/welcome');
        $this->client->request('POST', '/welcome', ['_token' => 'zly', 'role' => 'organizer']);
        $this->em->clear();
        self::assertSame('participant', $this->em->find(User::class, $user->getId())->getAppRole(), 'zła rola i zły token nic nie zmieniają');

        $this->client->request('POST', '/welcome', ['_token' => $token, 'role' => 'organizer']);
        $this->em->clear();
        self::assertSame('organizer', $this->em->find(User::class, $user->getId())->getAppRole());
    }
}
