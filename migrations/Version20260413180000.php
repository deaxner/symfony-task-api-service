<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260413180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add external project keys and time entry logging.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project ADD external_project_key VARCHAR(100) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2FB3D0EE5EC862F2 ON project (external_project_key)');
        $this->addSql('CREATE TABLE time_entry (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, task_id INT NOT NULL, project_id INT NOT NULL, started_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ended_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', minutes INT NOT NULL, billable TINYINT(1) NOT NULL DEFAULT 1, notes LONGTEXT DEFAULT NULL, cost_rate_snapshot NUMERIC(10, 2) DEFAULT NULL, bill_rate_snapshot NUMERIC(10, 2) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_867A299AA76ED395 (user_id), INDEX IDX_867A299A8DB60186 (task_id), INDEX IDX_867A299A166D1F9C (project_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE time_entry ADD CONSTRAINT FK_867A299AA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE time_entry ADD CONSTRAINT FK_867A299A8DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE time_entry ADD CONSTRAINT FK_867A299A166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE time_entry DROP FOREIGN KEY FK_867A299AA76ED395');
        $this->addSql('ALTER TABLE time_entry DROP FOREIGN KEY FK_867A299A8DB60186');
        $this->addSql('ALTER TABLE time_entry DROP FOREIGN KEY FK_867A299A166D1F9C');
        $this->addSql('DROP TABLE time_entry');
        $this->addSql('DROP INDEX UNIQ_2FB3D0EE5EC862F2 ON project');
        $this->addSql('ALTER TABLE project DROP external_project_key');
    }
}
