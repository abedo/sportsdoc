<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Rola administratora znika: dotychczasowi administratorzy zostają organizatorami. */
final class Version20261108200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Zamienia ROLE_ADMIN na ROLE_ORGANIZER w user.roles.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE user SET roles = REPLACE(roles, 'ROLE_ADMIN', 'ROLE_ORGANIZER')");
    }

    public function down(Schema $schema): void
    {
    }
}
