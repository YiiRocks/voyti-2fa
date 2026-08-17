<?php

declare(strict_types=1);

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;
use Yiisoft\Db\Schema\Column\ColumnBuilder;

/**
 * Creates the two-factor tables owned by yiirocks/voyti-2fa, kept out of the core user table:
 * `user_two_factor` (per-user state) and `user_backup_code` (single-use recovery codes).
 */
final class M260621102000_create_two_factor_tables implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%user_backup_code}}');
        $b->dropTable('{{%user_two_factor}}');
    }

    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%user_two_factor}}', [
            'user_id' => ColumnBuilder::integer()->notNull(),
            'enabled' => ColumnBuilder::boolean()->notNull()->defaultValue(false),
            'secret' => ColumnBuilder::string(255),
            'method' => ColumnBuilder::string(64),
            'PRIMARY KEY ([[user_id]])',
            'FOREIGN KEY ([[user_id]]) REFERENCES {{%user}} ([[id]]) ON DELETE CASCADE ON UPDATE RESTRICT',
        ]);

        $b->createTable('{{%user_backup_code}}', [
            'user_id' => ColumnBuilder::integer()->notNull(),
            'code_hash' => ColumnBuilder::string(255)->notNull(),
            'used_at' => ColumnBuilder::integer(),
            'created_at' => ColumnBuilder::integer()->notNull(),
            'PRIMARY KEY ([[user_id]], [[code_hash]])',
            'FOREIGN KEY ([[user_id]]) REFERENCES {{%user}} ([[id]]) ON DELETE CASCADE ON UPDATE RESTRICT',
        ]);
    }
}
