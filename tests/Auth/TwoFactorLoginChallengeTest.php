<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\tests\Auth;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\TwoFactor\Auth\TwoFactorLoginChallenge;
use YiiRocks\Voyti\TwoFactor\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\TwoFactor\tests\Support\FakeTwoFactorMethod;
use YiiRocks\Voyti\TwoFactor\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\TwoFactor\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\TwoFactor\TwoFactorMethodRegistry;
use Yiisoft\Session\SessionInterface;

final class TwoFactorLoginChallengeTest extends TestCase
{
    use DatabaseSetupTrait;
    use TestContainerTrait;
    use UserFactoryTrait;

    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testChallengeRendersConfirmationScreen(): void
    {
        // Code-based method: the step hook runs, the confirm screen renders with its code form, and
        // the pending credentials are stashed for ConfirmController.
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'totp');
        $method = new FakeTwoFactorMethod(name: 'totp');
        $container = $this->createTestContainer([
            TwoFactorMethodRegistry::class => new TwoFactorMethodRegistry([$method]),
        ]);
        $challenge = $container->get(TwoFactorLoginChallenge::class);

        $response = $challenge->challenge($user, true, new ServerRequest('POST', '/'));

        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($method->onAuthenticationStepStartCalled);
        self::assertStringContainsString('one-time-code', (string) $response->getBody());
        self::assertSame(
            ['login' => 'testuser', 'rememberMe' => true],
            $container->get(SessionInterface::class)->get('credentials'),
        );

        // Client-collected method: renders the fragment loader with the method's fragment URL
        // embedded in the page JS, not a code form.
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'webauthn');
        $method = new FakeTwoFactorMethod(
            name: 'webauthn',
            codeBased: false,
            confirmFragmentUrl: '//voyti/webauthn/confirm-fragment',
        );
        $container = $this->createTestContainer([
            TwoFactorMethodRegistry::class => new TwoFactorMethodRegistry([$method]),
        ]);

        $response = $container->get(TwoFactorLoginChallenge::class)
            ->challenge($user, false, new ServerRequest('POST', '/'));

        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        self::assertStringContainsString('voyti-session-confirm-method', $body);
        self::assertStringContainsString('confirm-fragment', $body);
        self::assertStringNotContainsString('one-time-code', $body);
    }

    public function testChallengeReturnsNullWhenConfirmationNotNeeded(): void
    {
        // 2FA disabled for the user: no challenge.
        $user = $this->createUser();
        $container = $this->createTestContainer([
            TwoFactorMethodRegistry::class => new TwoFactorMethodRegistry([new FakeTwoFactorMethod(name: 'totp')]),
        ]);

        $response = $container->get(TwoFactorLoginChallenge::class)
            ->challenge($user, false, new ServerRequest('POST', '/'));

        self::assertNull($response);

        // The stored method is no longer registered: no challenge.
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'totp');
        $container = $this->createTestContainer([
            TwoFactorMethodRegistry::class => new TwoFactorMethodRegistry([new FakeTwoFactorMethod(name: 'email')]),
        ]);

        $response = $container->get(TwoFactorLoginChallenge::class)
            ->challenge($user, false, new ServerRequest('POST', '/'));

        self::assertNull($response);
    }
}
