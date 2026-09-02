<?php

declare(strict_types=1);

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;
use Yiisoft\Db\Schema\Column\ColumnBuilder;

final class M260902120000_add_email_code_tracking implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    public function down(MigrationBuilder $b): void
    {
        $b->dropColumn('{{%user_two_factor}}', 'secret_attempts');
        $b->dropColumn('{{%user_two_factor}}', 'secret_created_at');
    }

    public function up(MigrationBuilder $b): void
    {
        $b->addColumn('{{%user_two_factor}}', 'secret_created_at', ColumnBuilder::integer());
        $b->addColumn('{{%user_two_factor}}', 'secret_attempts', ColumnBuilder::integer()->notNull()->defaultValue(0));
    }
}
