<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260413153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add projects plus task timeline fields.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE project (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, name VARCHAR(120) NOT NULL, color VARCHAR(20) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_2FB3D0EEA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EEA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task ADD project_id INT NOT NULL, ADD started_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD completed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('UPDATE task SET started_at = created_at WHERE status IN (\'in_progress\', \'done\')');
        $this->addSql('UPDATE task SET completed_at = updated_at WHERE status = \'done\'');
        $this->addSql('INSERT INTO project (user_id, name, color, description, created_at, updated_at) SELECT id, CONCAT(\'General backlog - \', email), \'#57b6ff\', \'Initial auto-generated project for migrated tasks.\', NOW(), NOW() FROM `user`');
        $this->addSql('UPDATE task t INNER JOIN project p ON p.user_id = t.user_id SET t.project_id = p.id');
        $this->addSql('CREATE INDEX IDX_527EDB25166D1F9C ON task (project_id)');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25166D1F9C');
        $this->addSql('DROP INDEX IDX_527EDB25166D1F9C ON task');
        $this->addSql('ALTER TABLE task DROP project_id, DROP started_at, DROP completed_at');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EEA76ED395');
        $this->addSql('DROP TABLE project');
    }
}
