<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\tests\Model;

use ReflectionProperty;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\TwoFactor\Model\UserTwoFactor;
use YiiRocks\Voyti\TwoFactor\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\TwoFactor\tests\TestCase;

final class UserTwoFactorTest extends TestCase
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

    public function testAccessors(): void
    {
        $entity = new UserTwoFactor();
        $entity->setUserId(42);
        $entity->setSecret('s3cr3t');
        $entity->setMethod('totp');
        $entity->setEnabled(true);

        self::assertSame(42, $entity->getUserId());
        self::assertSame('s3cr3t', $entity->getSecret());
        self::assertSame('totp', $entity->getMethod());
        self::assertTrue($entity->isEnabled());

        $entity->setEnabled(false);
        self::assertFalse($entity->isEnabled());
        $entity->setSecret(null);
        self::assertNull($entity->getSecret());
        $entity->setMethod(null);
        self::assertNull($entity->getMethod());
    }

    public function testDefaultValues(): void
    {
        $entity = new UserTwoFactor();
        self::assertSame(0, $entity->getUserId());
        self::assertFalse($entity->isEnabled());
        self::assertNull($entity->getSecret());
        self::assertNull($entity->getMethod());
    }

    public function testFindByUserId(): void
    {
        $record = new UserTwoFactor();
        $record->setUserId(7);
        $record->setMethod('email');
        $record->setEnabled(true);
        $record->save();

        $found = UserTwoFactor::findByUserId(7);
        self::assertNotNull($found);
        self::assertSame('email', $found->getMethod());
        self::assertTrue($found->isEnabled());

        // Reading a persisted record whose `enabled` is false must populate cleanly: the boolean
        // column returns a PHP bool, so the model property has to be bool too (regression: it was
        // typed int, and Active Record threw a TypeError populating false into it).
        $disabled = new UserTwoFactor();
        $disabled->setUserId(8);
        $disabled->setEnabled(false);
        $disabled->save();

        $foundDisabled = UserTwoFactor::findByUserId(8);
        self::assertNotNull($foundDisabled);
        self::assertFalse($foundDisabled->isEnabled());

        self::assertNull(UserTwoFactor::findByUserId(99));
    }

    public function testForUserReturnsExistingRecord(): void
    {
        $existing = new UserTwoFactor();
        $existing->setUserId(5);
        $existing->setMethod('totp');
        $existing->save();

        $found = UserTwoFactor::forUser($this->userWithId(5));
        self::assertSame('totp', $found->getMethod());
    }

    public function testForUserReturnsFreshRecordBoundToUser(): void
    {
        $fresh = UserTwoFactor::forUser($this->userWithId(8));
        self::assertSame(8, $fresh->getUserId());
        self::assertNull($fresh->getMethod());
        self::assertFalse($fresh->isEnabled());
    }

    public function testPrimaryKey(): void
    {
        self::assertSame(['user_id'], (new UserTwoFactor())->primaryKey());
    }

    private function userWithId(int $id): User
    {
        $user = new User();
        $ref = new ReflectionProperty(User::class, 'id');
        $ref->setValue($user, $id);

        return $user;
    }
}
