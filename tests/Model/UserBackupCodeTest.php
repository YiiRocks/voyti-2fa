<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\tests\Model;

use YiiRocks\Voyti\TwoFactor\Model\UserBackupCode;
use YiiRocks\Voyti\TwoFactor\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\TwoFactor\tests\TestCase;

final class UserBackupCodeTest extends TestCase
{
    use DatabaseSetupTrait;

    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testDefaultsAndPrimaryKey(): void
    {
        $entity = new UserBackupCode();
        self::assertSame(['user_id', 'code_hash'], $entity->primaryKey());
        self::assertSame(0, $entity->getUserId());
        self::assertSame('', $entity->getCodeHash());
        self::assertNull($entity->getUsedAt());
        self::assertSame(0, $entity->getCreatedAt());
    }

    public function testDeleteAllByUserIdRemovesOnlyThatUsersCodes(): void
    {
        $code1 = new UserBackupCode();
        $code1->setUserId(1);
        $code1->setCodeHash('hash1');
        $code1->setCreatedAt(time());
        $code1->save();

        $code2 = new UserBackupCode();
        $code2->setUserId(2);
        $code2->setCodeHash('hash2');
        $code2->setCreatedAt(time());
        $code2->save();

        UserBackupCode::deleteAllByUserId(1);

        self::assertCount(0, UserBackupCode::findUnusedByUserId(1));
        self::assertCount(1, UserBackupCode::findUnusedByUserId(2));
    }

    public function testFindUnusedByUserIdExcludesUsedCodes(): void
    {
        $unused = new UserBackupCode();
        $unused->setUserId(1);
        $unused->setCodeHash('unused-hash');
        $unused->setCreatedAt(time());
        $unused->save();

        $used = new UserBackupCode();
        $used->setUserId(1);
        $used->setCodeHash('used-hash');
        $used->setCreatedAt(time());
        $used->setUsedAt(time());
        $used->save();

        $found = UserBackupCode::findUnusedByUserId(1);

        self::assertCount(1, $found);
        self::assertSame('unused-hash', $found[0]->getCodeHash());
    }

    public function testMarkUsedConsumesOnceAndScopesToOwningUser(): void
    {
        // A code can be marked used only once, even from two concurrently fetched instances.
        $code = new UserBackupCode();
        $code->setUserId(1);
        $code->setCodeHash('race-hash');
        $code->setCreatedAt(time());
        $code->save();

        $first = UserBackupCode::findUnusedByUserId(1)[0];
        $second = UserBackupCode::findUnusedByUserId(1)[0];

        self::assertTrue($first->markUsed());
        self::assertFalse($second->markUsed());

        // Marking used scopes to the owning user: the same hash owned by another user stays unused.
        $ownCode = new UserBackupCode();
        $ownCode->setUserId(1);
        $ownCode->setCodeHash('shared-hash');
        $ownCode->setCreatedAt(time());
        $ownCode->save();

        $otherUsersCode = new UserBackupCode();
        $otherUsersCode->setUserId(2);
        $otherUsersCode->setCodeHash('shared-hash');
        $otherUsersCode->setCreatedAt(time());
        $otherUsersCode->save();

        self::assertTrue($ownCode->markUsed());

        $stillUnused = UserBackupCode::query()->where(['user_id' => 2, 'code_hash' => 'shared-hash'])->one();
        self::assertNotNull($stillUnused);
        self::assertNull($stillUnused->getUsedAt());
    }
}
