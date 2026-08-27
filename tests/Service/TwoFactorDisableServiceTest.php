<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\tests\Service;

use YiiRocks\Voyti\TwoFactor\Model\UserTwoFactor;
use YiiRocks\Voyti\TwoFactor\Service\BackupCodeService;
use YiiRocks\Voyti\TwoFactor\Service\TwoFactorDisableService;
use YiiRocks\Voyti\TwoFactor\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\TwoFactor\tests\Support\FakeTwoFactorMethod;
use YiiRocks\Voyti\TwoFactor\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\TwoFactor\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\TwoFactor\tests\TestCase;
use YiiRocks\Voyti\TwoFactor\TwoFactorMethodRegistry;

final class TwoFactorDisableServiceTest extends TestCase
{
    use DatabaseSetupTrait;
    use UserFactoryTrait;

    private BackupCodeService $backupCodeService;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->backupCodeService = new BackupCodeService(TestPasswordHasherFactory::create());
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testDisableRunsMethodHookClearsStateAndWipesBackupCodes(): void
    {
        // Stored method is registered: its onDisable() hook runs, not the default's.
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'totp', secret: 'STORED-SECRET');
        $this->backupCodeService->generate($user);
        $totp = new FakeTwoFactorMethod(name: 'totp');
        $webauthn = new FakeTwoFactorMethod(name: 'webauthn');
        $service = new TwoFactorDisableService(new TwoFactorMethodRegistry([$totp, $webauthn]), $this->backupCodeService);

        $service->disable($user);

        self::assertTrue($totp->onDisableCalled);
        self::assertFalse($webauthn->onDisableCalled);
        $twoFactor = UserTwoFactor::forUser($user);
        self::assertFalse($twoFactor->isEnabled());
        self::assertNull($twoFactor->getMethod());
        self::assertNull($twoFactor->getSecret());
        self::assertFalse($this->backupCodeService->hasUnused($user));

        // Stored method is no longer registered: falls back to the default rather than erroring.
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'gone');
        $default = new FakeTwoFactorMethod(name: 'totp');
        $service = new TwoFactorDisableService(new TwoFactorMethodRegistry([$default]), $this->backupCodeService);

        $service->disable($user);

        self::assertTrue($default->onDisableCalled);
        self::assertFalse(UserTwoFactor::forUser($user)->isEnabled());
    }
}
