<?php

namespace App\Tests\Functional;

use App\Entity\Game;
use App\Entity\Place;
use App\Entity\PlaceMember;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class DatabaseWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();

        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        // SQLite domyślnie ignoruje klucze obce; MySQL (InnoDB) ich pilnuje, więc włączamy to po utworzeniu schematu.
        $this->em->getConnection()->executeStatement('PRAGMA foreign_keys = ON');
    }

    protected function makeUser(string $email, string $name, bool $admin = false, bool $organizer = false): User
    {
        $roles = array_values(array_filter([$admin ? 'ROLE_ADMIN' : null, $organizer ? 'ROLE_ORGANIZER' : null]));
        $user = (new User())->setEmail($email)->setFullname($name)->setRoles($roles);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /** Dodaje osobę do miejsca (jako uczestnika albo organizatora tego miejsca). */
    protected function join(Place $place, User $user, bool $organizer = false): PlaceMember
    {
        $member = (new PlaceMember())->setPlace($place)->setUser($user)->setOrganizer($organizer);
        $this->em->persist($member);
        $this->em->flush();

        return $member;
    }

    protected function loginAs(User $user): User
    {
        $this->client->loginUser($user, 'main');

        return $user;
    }

    protected function makePlace(string $name = 'Orlik'): Place
    {
        $place = (new Place())->setName($name)->setAddress('ul. Sportowa 1')->setCity('Poznań')->setZip('60-001');
        $this->em->persist($place);
        $this->em->flush();

        return $place;
    }

    protected function makeGame(Place $place, int $cost = 30, string $date = '2026-11-03'): Game
    {
        $game = (new Game())->setPlace($place)->setCost($cost)->setCreatedAt(new \DateTime($date));
        $this->em->persist($game);
        $this->em->flush();

        return $game;
    }
}
