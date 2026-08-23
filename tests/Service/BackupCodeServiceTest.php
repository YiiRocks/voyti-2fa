<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\tests\Service;

use ReflectionProperty;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\TwoFactor\Model\UserBackupCode;
use YiiRocks\Voyti\TwoFactor\Service\BackupCodeService;
use YiiRocks\Voyti\TwoFactor\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\TwoFactor\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\TwoFactor\tests\TestCase;

final class BackupCodeServiceTest extends TestCase
{
    use DatabaseSetupTrait;

    private BackupCodeService $service;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->service = new BackupCodeService(TestPasswordHasherFactory::create());
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testClearRemovesAllCodes(): void
    {
        $user = $this->userWithId(1);
        $this->service->generate($user, count: 4);

        $this->service->clear($user);

        self::assertFalse($this->service->hasUnused($user));
        self::assertCount(0, UserBackupCode::findUnusedByUserId(1));
    }

    public function testConsumeValidatesCodesAndSingleUse(): void
    {
        // A valid code is accepted exactly once; the second attempt with it fails.
        $user = $this->userWithId(1);
        $codes = $this->service->generate($user, count: 3);

        self::assertTrue($this->service->consume($user, $codes[1]));
        self::assertFalse($this->service->consume($user, $codes[1]));
        self::assertCount(2, UserBackupCode::findUnusedByUserId(1));

        // Unknown and empty codes are rejected.
        $user = $this->userWithId(1);
        $this->service->generate($user, count: 2);

        self::assertFalse($this->service->consume($user, 'NOTACODE99'));
        self::assertFalse($this->service->consume($user, ''));
    }

    public function testGeneratePersistsTenUppercaseHashedCodesAndReplacesExistingOnes(): void
    {
        // Defaults: ten ten-char uppercase codes persisted for the user; plaintext returned while
        // only hashes are stored.
        $user = $this->userWithId(1);

        $codes = $this->service->generate($user);

        self::assertCount(10, $codes);
        $stored = UserBackupCode::findUnusedByUserId(1);
        self::assertCount(10, $stored);
        foreach ($codes as $code) {
            self::assertSame(10, strlen($code));
            self::assertSame(strtoupper($code), $code);
        }
        foreach ($stored as $backupCode) {
            self::assertGreaterThan(0, $backupCode->getCreatedAt());
        }
        self::assertNotContains($codes[0], array_map(
            static fn(UserBackupCode $c): string => $c->getCodeHash(),
            $stored,
        ));
        self::assertTrue($this->service->hasUnused($user));

        // Generating again replaces any previously stored codes.
        $this->service->generate($user, count: 3);
        $this->service->generate($user, count: 2);

        self::assertCount(2, UserBackupCode::findUnusedByUserId(1));
    }

    private function userWithId(int $id): User
    {
        $user = new User();
        $ref = new ReflectionProperty(User::class, 'id');
        $ref->setValue($user, $id);

        return $user;
    }
}
