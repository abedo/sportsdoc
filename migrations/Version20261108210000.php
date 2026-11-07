<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Wyrównuje nazwy indeksów i typ user.roles z mapowaniem Doctrine (doctrine:schema:validate bez różnic). */
final class Version20261108210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Nazwy indeksów place_member, user_identity, payment oraz user.roles jako JSON.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment RENAME INDEX idx_payment_place TO IDX_6D28840DDA6A219');
        $this->addSql('ALTER TABLE user CHANGE email email VARCHAR(180) DEFAULT NULL, CHANGE roles roles JSON NOT NULL');
        $this->addSql('ALTER TABLE place_member RENAME INDEX idx_pm_place TO IDX_D22AFD11DA6A219');
        $this->addSql('ALTER TABLE place_member RENAME INDEX idx_pm_user TO IDX_D22AFD11A76ED395');
        $this->addSql('ALTER TABLE user_identity RENAME INDEX idx_ui_user TO IDX_8A180DC4A76ED395');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment RENAME INDEX IDX_6D28840DDA6A219 TO idx_payment_place');
        $this->addSql('ALTER TABLE place_member RENAME INDEX IDX_D22AFD11DA6A219 TO idx_pm_place');
        $this->addSql('ALTER TABLE place_member RENAME INDEX IDX_D22AFD11A76ED395 TO idx_pm_user');
        $this->addSql('ALTER TABLE user_identity RENAME INDEX IDX_8A180DC4A76ED395 TO idx_ui_user');
    }
}
