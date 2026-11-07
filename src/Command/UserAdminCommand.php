<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:admin', description: 'Nadaje (lub odbiera) rolę organizatora albo administratora osobie o podanym e-mailu.')]
final class UserAdminCommand
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Adres e-mail osoby (musi się wcześniej zalogować lub zostać dodana w panelu)')] string $email,
        #[Option('Odbierz rolę zamiast ją nadawać')] bool $remove = false,
        #[Option('Rola administratora serwisu (domyślnie: organizator, który może zakładać miejsca)')] bool $admin = false,
    ): int {
        $user = $this->users->findOneBy(['email' => $email]);
        if (null === $user) {
            $io->error(sprintf('Nie ma osoby z adresem %s. Najpierw musi się zalogować lub zostać dodana w panelu.', $email));

            return Command::FAILURE;
        }

        $role = $admin ? 'ROLE_ADMIN' : 'ROLE_ORGANIZER';
        $roles = array_values(array_diff($user->getRoles(), ['ROLE_USER', $role]));
        if (!$remove) {
            $roles[] = $role;
        }
        $user->setRoles($roles);
        $this->em->flush();

        $io->success(sprintf('%s: rola %s %s.', $email, $admin ? 'administratora' : 'organizatora', $remove ? 'odebrana' : 'nadana'));

        return Command::SUCCESS;
    }
}
