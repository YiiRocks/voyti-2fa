<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\tests\Support;

use Psr\SimpleCache\CacheInterface;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver;
use Yiisoft\Db\Sqlite\Dsn;

/**
 * In-memory SQLite with the tables the 2FA flows touch: a plain `user` (no auth_tf_* columns - 2FA
 * state lives in `user_two_factor`), plus `user_two_factor` and `user_backup_code`.
 */
trait DatabaseSetupTrait
{
    private ?ConnectionInterface $dbConnection = null;

    protected function setUpDatabase(): void
    {
        $dsn = new Dsn('sqlite', ':memory:');
        $driver = new Driver($dsn);
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('set')->willReturn(true);
        $cache->method('get')->willReturn(null);
        $schemaCache = new SchemaCache($cache);
        $schemaCache->setEnabled(false);
        $connection = new SqliteConnection($driver, $schemaCache);
        ConnectionProvider::set($connection);
        $this->dbConnection = $connection;

        $connection->createCommand('
            CREATE TABLE "user" (
                "id" INTEGER PRIMARY KEY AUTOINCREMENT,
                "username" VARCHAR(255) NOT NULL,
                "email" VARCHAR(255) NOT NULL,
                "password_hash" VARCHAR(255) NOT NULL,
                "auth_key" VARCHAR(32) NOT NULL,
                "blocked_at" INTEGER,
                "confirmed_at" INTEGER,
                "created_at" INTEGER NOT NULL,
                "flags" INTEGER NOT NULL DEFAULT 0,
                "gdpr_consent" INTEGER NOT NULL DEFAULT 0,
                "gdpr_consent_date" INTEGER,
                "anonymized" INTEGER NOT NULL DEFAULT 0,
                "last_login_at" INTEGER,
                "last_login_ip" VARCHAR(45),
                "password_changed_at" INTEGER,
                "registration_ip" VARCHAR(45),
                "unconfirmed_email" VARCHAR(255),
                "updated_at" INTEGER NOT NULL
            )
        ')->execute();

        $connection->createCommand('
            CREATE TABLE "user_two_factor" (
                "user_id" INTEGER NOT NULL PRIMARY KEY,
                "enabled" boolean NOT NULL DEFAULT 0,
                "secret" VARCHAR(255),
                "method" VARCHAR(64)
            )
        ')->execute();

        $connection->createCommand('
            CREATE TABLE "user_backup_code" (
                "user_id" INTEGER NOT NULL,
                "code_hash" VARCHAR(255) NOT NULL,
                "used_at" INTEGER,
                "created_at" INTEGER NOT NULL,
                PRIMARY KEY ("user_id", "code_hash")
            )
        ')->execute();
    }

    protected function tearDownDatabase(): void
    {
        if ($this->dbConnection !== null) {
            $this->dbConnection->createCommand('DROP TABLE IF EXISTS "user_backup_code"')->execute();
            $this->dbConnection->createCommand('DROP TABLE IF EXISTS "user_two_factor"')->execute();
            $this->dbConnection->createCommand('DROP TABLE IF EXISTS "user"')->execute();
        }
        ConnectionProvider::clear();
        $this->dbConnection = null;
    }
}
