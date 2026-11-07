<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Wpłaty zgłoszone przez osoby wymagają potwierdzenia; dotychczasowe wpisy zostają potwierdzone. */
final class Version20261107200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Dodaje payment.confirmed (domyślnie 1, więc istniejące wpłaty są potwierdzone).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment ADD confirmed TINYINT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment DROP confirmed');
    }
}
