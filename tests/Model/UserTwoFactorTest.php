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

    public function testAccessorsAndPrimaryKey(): void
    {
        $entity = new UserTwoFactor();
        self::assertSame(['user_id'], $entity->primaryKey());
        self::assertSame(0, $entity->getUserId());
        self::assertFalse($entity->isEnabled());
        self::assertNull($entity->getSecret());
        self::assertNull($entity->getMethod());

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

    public function testForUser(): void
    {
        // Returns the existing persisted record.
        $existing = new UserTwoFactor();
        $existing->setUserId(5);
        $existing->setMethod('totp');
        $existing->save();

        $found = UserTwoFactor::forUser($this->userWithId(5));
        self::assertSame('totp', $found->getMethod());

        // Returns a fresh record bound to the user when none exists yet.
        $fresh = UserTwoFactor::forUser($this->userWithId(8));
        self::assertSame(8, $fresh->getUserId());
        self::assertNull($fresh->getMethod());
        self::assertFalse($fresh->isEnabled());
    }

    public function testRecordEmailAttemptEnforcesLifespanAndMaximum(): void
    {
        $record = new UserTwoFactor();
        $record->setUserId(5);
        $record->setSecret('123456');
        $record->setSecretCreatedAt(time());
        $record->save();

        $otherRecord = new UserTwoFactor();
        $otherRecord->setUserId(6);
        $otherRecord->setSecret('654321');
        $otherRecord->setSecretCreatedAt(time());
        $otherRecord->save();

        self::assertTrue($record->recordEmailAttempt(300, 2));
        self::assertSame(1, $this->secretAttempts($record));
        self::assertTrue($record->recordEmailAttempt(300, 2));
        self::assertSame(2, $this->secretAttempts($record));
        self::assertFalse($record->recordEmailAttempt(300, 2));
        self::assertTrue($otherRecord->recordEmailAttempt(300, 2));

        $record->setSecretCreatedAt(time() - 301);
        $record->setSecretAttempts(0);
        $record->save();

        self::assertFalse($record->recordEmailAttempt(300, 2));
    }

    private function secretAttempts(UserTwoFactor $record): int
    {
        $property = new ReflectionProperty(UserTwoFactor::class, 'secret_attempts');

        return $property->getValue($record);
    }

    private function userWithId(int $id): User
    {
        $user = new User();
        $ref = new ReflectionProperty(User::class, 'id');
        $ref->setValue($user, $id);

        return $user;
    }
}
