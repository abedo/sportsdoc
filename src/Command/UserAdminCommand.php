<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:admin', description: 'Nadaje (lub odbiera) rolę administratora/organizatora osobie o podanym e-mailu.')]
final class UserAdminCommand
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Adres e-mail osoby (musi się wcześniej zalogować przez Google)')] string $email,
        #[Option('Odbierz rolę administratora')] bool $remove = false,
    ): int {
        $user = $this->users->findOneBy(['email' => $email]);
        if (null === $user) {
            $io->error(sprintf('Nie ma osoby z adresem %s. Najpierw musi się zalogować lub zostać dodana w panelu.', $email));

            return Command::FAILURE;
        }

        $roles = array_values(array_diff($user->getRoles(), ['ROLE_USER', 'ROLE_ADMIN']));
        if (!$remove) {
            $roles[] = 'ROLE_ADMIN';
        }
        $user->setRoles($roles);
        $this->em->flush();

        $io->success(sprintf('%s: %s.', $email, $remove ? 'rola administratora odebrana' : 'jest teraz administratorem'));

        return Command::SUCCESS;
    }
}
