<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\tests\View;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\TwoFactor\Auth\TwoFactorLoginChallenge;
use YiiRocks\Voyti\TwoFactor\ResolvesPluginViewPath;
use YiiRocks\Voyti\TwoFactor\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\TwoFactor\tests\Support\FakeTwoFactorMethod;
use YiiRocks\Voyti\TwoFactor\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\TwoFactor\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\TwoFactor\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\TwoFactor\TwoFactorMethodRegistry;
use YiiRocks\Voyti\VoytiConfig;

/**
 * Covers {@see ResolvesPluginViewPath}: a host's configured `viewPath`
 * override takes precedence over this package's bundled views.
 */
final class ResolvesPluginViewPathTest extends TestCase
{
    use DatabaseSetupTrait;
    use TestContainerTrait;
    use UserFactoryTrait;

    private string $overrideDir = '';

    protected function setUp(): void
    {
        $this->setUpDatabase();

        $this->overrideDir = sys_get_temp_dir() . '/voyti-2fa-viewpath-' . uniqid('', true);
        mkdir($this->overrideDir . '/two-factor', 0o777, true);
        file_put_contents(
            $this->overrideDir . '/two-factor/confirm.php',
            '<?php echo "HOST-OVERRIDE-CONFIRM";',
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->overrideDir . '/two-factor/confirm.php');
        @rmdir($this->overrideDir . '/two-factor');
        @rmdir($this->overrideDir);
        $this->tearDownDatabase();
    }

    public function testFallsBackToPluginViewWhenOverrideDirLacksTheView(): void
    {
        // viewPath is set but does not contain this view, so the package's own bundled view is used.
        $emptyDir = sys_get_temp_dir() . '/voyti-2fa-empty-' . uniqid('', true);
        mkdir($emptyDir, 0o777, true);

        try {
            $user = $this->createUser();
            $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'totp');
            $container = $this->createTestContainer([
                VoytiConfig::class => VoytiConfigFactory::create(viewPath: $emptyDir),
                TwoFactorMethodRegistry::class => new TwoFactorMethodRegistry([new FakeTwoFactorMethod(name: 'totp')]),
            ]);

            $response = $container->get(TwoFactorLoginChallenge::class)
                ->challenge($user, false, new ServerRequest('POST', '/'));

            self::assertNotNull($response);
            self::assertStringContainsString('one-time-code', (string) $response->getBody());
        } finally {
            @rmdir($emptyDir);
        }
    }

    public function testHostViewPathOverrideWins(): void
    {
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'totp');
        $container = $this->createTestContainer([
            VoytiConfig::class => VoytiConfigFactory::create(viewPath: $this->overrideDir),
            TwoFactorMethodRegistry::class => new TwoFactorMethodRegistry([new FakeTwoFactorMethod(name: 'totp')]),
        ]);

        $response = $container->get(TwoFactorLoginChallenge::class)
            ->challenge($user, false, new ServerRequest('POST', '/'));

        self::assertNotNull($response);
        self::assertStringContainsString('HOST-OVERRIDE-CONFIRM', (string) $response->getBody());
    }
}
