<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Role per miejsce (place_member), logowanie przez wielu dostawców (user_identity),
 * opcjonalny e-mail i wpłaty przypisane do miejsca.
 *
 * Wpłaty zapisane przed tą migracją dostają miejsce pierwszej gry osoby albo pierwsze miejsce; istniejące
 * osoby zostają uczestnikami miejsc, w których grały lub płaciły, a administratorzy organizatorami wszystkich miejsc.
 */
final class Version20261108100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Dodaje place_member i user_identity, payment.place_id oraz opcjonalny user.email.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE place_member (id INT AUTO_INCREMENT NOT NULL, place_id INT NOT NULL, user_id INT NOT NULL, organizer TINYINT DEFAULT 0 NOT NULL, joined_at DATETIME NOT NULL, INDEX IDX_PM_PLACE (place_id), INDEX IDX_PM_USER (user_id), UNIQUE INDEX uniq_place_member (place_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE place_member ADD CONSTRAINT FK_PM_PLACE FOREIGN KEY (place_id) REFERENCES place (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE place_member ADD CONSTRAINT FK_PM_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE user_identity (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, provider VARCHAR(32) NOT NULL, subject VARCHAR(191) NOT NULL, INDEX IDX_UI_USER (user_id), UNIQUE INDEX uniq_identity (provider, subject), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_identity ADD CONSTRAINT FK_UI_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE user CHANGE email email VARCHAR(180) DEFAULT NULL');

        $this->addSql('ALTER TABLE payment ADD place_id INT DEFAULT NULL');
        $this->addSql('UPDATE payment p SET p.place_id = COALESCE((SELECT g.place_id FROM attendance a JOIN game g ON g.id = a.game_id WHERE a.user_id = p.user_id ORDER BY g.id LIMIT 1), (SELECT MIN(id) FROM place))');
        $this->addSql('DELETE FROM payment WHERE place_id IS NULL');
        $this->addSql('ALTER TABLE payment CHANGE place_id place_id INT NOT NULL');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_PAYMENT_PLACE FOREIGN KEY (place_id) REFERENCES place (id)');
        $this->addSql('CREATE INDEX IDX_PAYMENT_PLACE ON payment (place_id)');

        // Dotychczasowi uczestnicy: członkowie miejsc, w których byli albo płacili.
        $this->addSql('INSERT INTO place_member (place_id, user_id, organizer, joined_at) SELECT DISTINCT g.place_id, a.user_id, 0, NOW() FROM attendance a JOIN game g ON g.id = a.game_id');
        $this->addSql('INSERT IGNORE INTO place_member (place_id, user_id, organizer, joined_at) SELECT DISTINCT place_id, user_id, 0, NOW() FROM payment');
        // Administratorzy (dotychczasowi organizatorzy) zarządzają wszystkimi istniejącymi miejscami.
        $this->addSql('INSERT INTO place_member (place_id, user_id, organizer, joined_at) SELECT p.id, u.id, 1, NOW() FROM place p JOIN user u ON u.roles LIKE \'%ROLE_ADMIN%\' ON DUPLICATE KEY UPDATE organizer = 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_PAYMENT_PLACE');
        $this->addSql('DROP INDEX IDX_PAYMENT_PLACE ON payment');
        $this->addSql('ALTER TABLE payment DROP place_id');
        $this->addSql('ALTER TABLE user CHANGE email email VARCHAR(180) NOT NULL');
        $this->addSql('DROP TABLE user_identity');
        $this->addSql('DROP TABLE place_member');
    }
}
