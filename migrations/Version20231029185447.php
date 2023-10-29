<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20231029185447 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add worker and messenger tables';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('messenger_messages');
        // add an internal option to mark that we created this & the non-namespaced table name
        $table->addOption('_symfony_messenger_table_name', 'messenger_messages');
        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setNotnull(true);
        $table->addColumn('body', Types::TEXT)
            ->setNotnull(true);
        $table->addColumn('headers', Types::TEXT)
            ->setNotnull(true);
        $table->addColumn('queue_name', Types::STRING)
            ->setLength(190) // MySQL 5.6 only supports 191 characters on an indexed column in utf8mb4 mode
            ->setNotnull(true);
        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(true);
        $table->addColumn('available_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(true);
        $table->addColumn('delivered_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(false);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['queue_name']);
        $table->addIndex(['available_at']);
        $table->addIndex(['delivered_at']);

        $this->addSql('CREATE SEQUENCE worker_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE worker (id INT NOT NULL, transports JSON NOT NULL, message_limit INT NOT NULL, failure_limit INT NOT NULL, memory_limit INT NOT NULL, time_limit INT NOT NULL, sleep INT NOT NULL, queues JSON NOT NULL, reset BOOLEAN NOT NULL, status VARCHAR(255) NOT NULL, handled INT NOT NULL, failed INT NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_heartbeat TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN worker.started_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN worker.last_heartbeat IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SEQUENCE worker_id_seq CASCADE');
        $this->addSql('DROP TABLE worker');

        $schema->dropTable('messenger_messages');
    }
}
