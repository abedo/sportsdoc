<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Lista obecności: tabela attendance (osoba, gra, był / nie był). */
final class Version20261107190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Dodaje tabelę attendance (obecność osoby w grze).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE attendance (id INT AUTO_INCREMENT NOT NULL, present TINYINT NOT NULL, game_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX uniq_attendance_game_user (game_id, user_id), INDEX IDX_6DE30D91E48FD905 (game_id), INDEX IDX_6DE30D91A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE attendance ADD CONSTRAINT FK_6DE30D91E48FD905 FOREIGN KEY (game_id) REFERENCES game (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE attendance ADD CONSTRAINT FK_6DE30D91A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attendance DROP FOREIGN KEY FK_6DE30D91E48FD905');
        $this->addSql('ALTER TABLE attendance DROP FOREIGN KEY FK_6DE30D91A76ED395');
        $this->addSql('DROP TABLE attendance');
    }
}
