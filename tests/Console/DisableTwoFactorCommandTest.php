<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\tests\Console;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Tester\CommandTester;
use YiiRocks\Voyti\TwoFactor\Console\DisableTwoFactorCommand;
use YiiRocks\Voyti\TwoFactor\Model\UserTwoFactor;
use YiiRocks\Voyti\TwoFactor\Service\BackupCodeService;
use YiiRocks\Voyti\TwoFactor\Service\TwoFactorDisableService;
use YiiRocks\Voyti\TwoFactor\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\TwoFactor\tests\Support\FakeTwoFactorMethod;
use YiiRocks\Voyti\TwoFactor\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\TwoFactor\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\TwoFactor\tests\TestCase;
use YiiRocks\Voyti\TwoFactor\TwoFactorMethodRegistry;
use Yiisoft\Yii\Console\ExitCode;

final class DisableTwoFactorCommandTest extends TestCase
{
    use DatabaseSetupTrait;
    use UserFactoryTrait;

    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public static function executeProvider(): iterable
    {
        yield 'no identifying option' => [
            [], false, null, ExitCode::USAGE, 'No identifying option provided.', false,
        ];
        yield 'non-existent user' => [
            ['--email' => 'missing@example.com'], false, null, ExitCode::NOUSER, 'User not found.', false,
        ];
        yield 'existing user, 2FA not enabled' => [
            ['--username' => 'testuser'], true, null, ExitCode::OK, 'Two-factor authentication is not enabled for this user.', false,
        ];
        yield 'existing user by id, 2FA enabled' => [
            ['--id' => 'self'], true, 'totp', ExitCode::OK, 'Two-factor authentication disabled.', true,
        ];
        yield 'existing user by email, 2FA enabled' => [
            ['--email' => 'test@example.com'], true, 'totp', ExitCode::OK, 'Two-factor authentication disabled.', true,
        ];
        yield 'existing user by username, 2FA enabled' => [
            ['--username' => 'testuser'], true, 'totp', ExitCode::OK, 'Two-factor authentication disabled.', true,
        ];
    }

    public function testConfiguration(): void
    {
        $command = $this->createCommand(new FakeTwoFactorMethod());

        self::assertSame('voyti:2fa:disable', $command->getName());
        self::assertSame('Disable two-factor authentication for a user', $command->getDescription());
    }

    /**
     * @param array<string, string> $options
     */
    #[DataProvider('executeProvider')]
    public function testExecute(
        array $options,
        bool $createUser,
        ?string $twoFactorMethod,
        int $expectedCode,
        string $expectedMessage,
        bool $expectedOnDisableCalled,
    ): void {
        $user = $createUser ? $this->createUser() : null;
        if ($user !== null) {
            if ($twoFactorMethod !== null) {
                $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: $twoFactorMethod);
            }
            if (isset($options['--id'])) {
                $options['--id'] = (string) $user->getId();
            }
        }

        $method = new FakeTwoFactorMethod(name: 'totp');
        $tester = new CommandTester($this->createCommand($method));
        $result = $tester->execute($options);

        self::assertSame($expectedCode, $result);
        self::assertStringContainsString($expectedMessage, $tester->getDisplay());
        self::assertSame($expectedOnDisableCalled, $method->onDisableCalled);
        if ($user !== null) {
            self::assertFalse(UserTwoFactor::forUser($user)->isEnabled());
        }
    }

    private function createCommand(FakeTwoFactorMethod $method): DisableTwoFactorCommand
    {
        $backupCodeService = new BackupCodeService(TestPasswordHasherFactory::create());

        return new DisableTwoFactorCommand(
            new TwoFactorDisableService(new TwoFactorMethodRegistry([$method]), $backupCodeService),
        );
    }
}
