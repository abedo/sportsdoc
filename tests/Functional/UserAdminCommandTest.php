<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class UserAdminCommandTest extends DatabaseWebTestCase
{
    public function testPromotesAndDemotesByEmail(): void
    {
        $user = $this->makeUser('jan@example.com', 'Jan Kowalski');
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:user:admin'));

        self::assertSame(0, $tester->execute(['email' => 'jan@example.com']));
        $this->em->clear();
        self::assertContains('ROLE_ADMIN', $this->em->find($user::class, $user->getId())->getRoles());

        self::assertSame(0, $tester->execute(['email' => 'jan@example.com', '--remove' => true]));
        $this->em->clear();
        self::assertNotContains('ROLE_ADMIN', $this->em->find($user::class, $user->getId())->getRoles());
    }

    public function testUnknownEmailFails(): void
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:user:admin'));
        self::assertSame(1, $tester->execute(['email' => 'nikt@example.com']));
    }
}
