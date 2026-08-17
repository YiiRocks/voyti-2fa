<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\tests\Controller;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\TwoFactor\Controller\TwoFactorController;
use YiiRocks\Voyti\TwoFactor\Model\UserTwoFactor;
use YiiRocks\Voyti\TwoFactor\Service\BackupCodeService;
use YiiRocks\Voyti\TwoFactor\tests\Support\CurrentUserTrait;
use YiiRocks\Voyti\TwoFactor\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\TwoFactor\tests\Support\FakeTwoFactorMethod;
use YiiRocks\Voyti\TwoFactor\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\TwoFactor\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\TwoFactor\TwoFactorMethodRegistry;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\User\CurrentUser;

final class TwoFactorControllerTest extends TestCase
{
    use CurrentUserTrait;
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

    public function testDisableFailureForNonCodeBasedMethodShowsError(): void
    {
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'webauthn');
        $method = new FakeTwoFactorMethod(name: 'webauthn', codeBased: false, errorMessage: 'No key.', verifyResult: false);
        [, $controller] = $this->build($user, $method);

        $response = $controller->disable($this->postBody('{"assertion":"bad"}'), '');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('No key.', (string) $response->getBody());
        self::assertTrue(UserTwoFactor::forUser($user)->isEnabled());
        self::assertFalse($method->onDisableCalled);
    }

    public function testDisableFailureKeepsTwoFactorEnabledAndShowsError(): void
    {
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'totp');
        $method = new FakeTwoFactorMethod(name: 'totp', errorMessage: 'Bad code.', verifyResult: false);
        [$container, $controller] = $this->build($user, $method);

        $response = $controller->disable($this->post(), 'wrong');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Bad code.', (string) $response->getBody());
        self::assertTrue(UserTwoFactor::forUser($user)->isEnabled());
        self::assertFalse($method->onDisableCalled);
    }

    public function testDisableSendCode(): void
    {
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'email');

        // Not requiring code delivery: no send step, straight redirect.
        $onDemand = new FakeTwoFactorMethod(name: 'email', codeDelivery: false);
        [, $controller] = $this->build($user, $onDemand);
        $response = $controller->disableSendCode();
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('//voyti/user-two-factor', $response->getHeaderLine('Location'));
        self::assertFalse($onDemand->onAuthenticationStepStartCalled);

        // Requiring code delivery: starts the method's step and renders the code-entry (disable)
        // form, not the "send a code" pre-step.
        $delivered = new FakeTwoFactorMethod(name: 'email', codeDelivery: true);
        [, $controller] = $this->build($user, $delivered);
        $response = $controller->disableSendCode();
        $body = (string) $response->getBody();
        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($delivered->onAuthenticationStepStartCalled);
        self::assertStringNotContainsString('user-two-factor-disable-send-code', $body);
    }

    public function testDisableSendCodeRedirectsWhenNotEnabled(): void
    {
        $user = $this->createUser();
        [, $controller] = $this->build($user, new FakeTwoFactorMethod(name: 'email', codeDelivery: true));

        $response = $controller->disableSendCode();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('//voyti/user-two-factor', $response->getHeaderLine('Location'));
    }

    public function testDisableSuccessViaBackupCode(): void
    {
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'totp');
        $method = new FakeTwoFactorMethod(name: 'totp', verifyResult: false);
        [$container, $controller] = $this->build($user, $method);

        /** @var BackupCodeService $backupCodes */
        $backupCodes = $container->get(BackupCodeService::class);
        $codes = $backupCodes->generate($user);

        $response = $controller->disable($this->post(), $codes[0]);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('//voyti/user-two-factor', $response->getHeaderLine('Location'));
        self::assertTrue($method->onDisableCalled);
        $twoFactor = UserTwoFactor::forUser($user);
        self::assertFalse($twoFactor->isEnabled());
        self::assertNull($twoFactor->getMethod());
        self::assertFalse($backupCodes->hasUnused($user));
    }

    public function testDisableSuccessViaMethodCode(): void
    {
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'totp', secret: 'STORED-SECRET');
        $method = new FakeTwoFactorMethod(name: 'totp', verifyResult: true);
        [$container, $controller] = $this->build($user, $method);

        $response = $controller->disable($this->post(), '123456');

        self::assertSame(302, $response->getStatusCode());
        self::assertTrue($method->onDisableCalled);
        self::assertSame(['code' => '123456'], $method->lastVerifyData);
        $twoFactor = UserTwoFactor::forUser($user);
        self::assertFalse($twoFactor->isEnabled());
        // The stored method secret is cleared on disable.
        self::assertNull($twoFactor->getSecret());
        self::assertSame(
            'Two-factor authentication has been disabled',
            $container->get(FlashInterface::class)->get('success'),
        );
    }

    public function testDisableSuccessViaPayloadForNonCodeBasedMethod(): void
    {
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'webauthn');
        $method = new FakeTwoFactorMethod(name: 'webauthn', codeBased: false, verifyResult: true);
        [, $controller] = $this->build($user, $method);

        $response = $controller->disable($this->postBody('{"assertion":"ok"}'), '');

        self::assertSame(302, $response->getStatusCode());
        self::assertTrue($method->onDisableCalled);
        // The raw request body is forwarded to the method as the assertion payload (with the request
        // host for the relying-party id); no code needed.
        self::assertSame(['payload' => '{"assertion":"ok"}', 'domain' => ''], $method->lastVerifyData);
        self::assertFalse(UserTwoFactor::forUser($user)->isEnabled());
    }

    public function testEnableAlreadyEnabledRedirectsWithFlash(): void
    {
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'totp');
        [$container, $controller] = $this->build($user, new FakeTwoFactorMethod(name: 'totp'));

        $response = $controller->enable('totp', '123456');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('//voyti/user-two-factor', $response->getHeaderLine('Location'));
        self::assertSame(
            'Two-factor authentication has been enabled',
            $container->get(FlashInterface::class)->get('success'),
        );
    }

    public function testEnableNonCodeBasedMethodRedirects(): void
    {
        $user = $this->createUser();
        $method = new FakeTwoFactorMethod(name: 'webauthn', codeBased: false);
        [, $controller] = $this->build($user, $method);

        $response = $controller->enable('webauthn', '');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('//voyti/user-two-factor', $response->getHeaderLine('Location'));
        self::assertFalse(UserTwoFactor::forUser($user)->isEnabled());
    }

    public function testEnableSuccessStoresMethodAndShowsBackupCodes(): void
    {
        $user = $this->createUser();
        $method = new FakeTwoFactorMethod(name: 'totp', verifyResult: true);
        [$container, $controller] = $this->build($user, $method);

        // Unknown method falls back to the registered default.
        $response = $controller->enable('nonexistent', '123456');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['code' => '123456'], $method->lastVerifyData);
        // The backup-codes screen renders fully (its shared chrome + code list).
        self::assertStringContainsString('Backup Codes', (string) $response->getBody());
        $twoFactor = UserTwoFactor::forUser($user);
        self::assertTrue($twoFactor->isEnabled());
        self::assertSame('totp', $twoFactor->getMethod());
        /** @var BackupCodeService $backupCodes */
        $backupCodes = $container->get(BackupCodeService::class);
        self::assertTrue($backupCodes->hasUnused($user));
    }

    public function testEnableVerificationFailureShowsError(): void
    {
        $user = $this->createUser();
        $method = new FakeTwoFactorMethod(name: 'totp', errorMessage: '', verifyResult: false);
        [, $controller] = $this->build($user, $method);

        $response = $controller->enable('totp', 'wrong');

        self::assertSame(200, $response->getStatusCode());
        // Empty method error falls back to the package's generic validator message.
        self::assertStringContainsString('verification code', (string) $response->getBody());
        self::assertFalse(UserTwoFactor::forUser($user)->isEnabled());
    }

    public function testEnableWithNoAvailableMethodsRedirects(): void
    {
        // Base package installed without any method package: enable can't fall back to a default, so
        // it redirects to the index rather than letting getDefault() throw.
        $user = $this->createUser();
        [, $controller] = $this->buildWith($user, []);

        $response = $controller->enable('', '');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('//voyti/user-two-factor', $response->getHeaderLine('Location'));
        self::assertFalse(UserTwoFactor::forUser($user)->isEnabled());
    }

    public function testIndexEnabledResolvesStoredMethodOverDefault(): void
    {
        // Two methods registered; the user's stored method is the non-default one. The index must
        // resolve the stored method, not fall back to the default (the first registered).
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'webauthn');
        [, $controller] = $this->buildWith($user, [
            new FakeTwoFactorMethod(name: 'totp', enabledWithMethodName: 'Authenticator'),
            new FakeTwoFactorMethod(name: 'webauthn', enabledWithMethodName: 'Security Key'),
        ]);

        $response = $controller->index();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Security Key', (string) $response->getBody());
    }

    public function testIndexEnabledShowsDisableForm(): void
    {
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'totp');
        [, $controller] = $this->build($user, new FakeTwoFactorMethod(name: 'totp'));

        $response = $controller->index();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('//voyti/user-two-factor-disable', (string) $response->getBody());
    }

    public function testIndexEnabledUnregisteredStoredMethodFallsBackToDefault(): void
    {
        // The stored method is no longer registered (its package was removed); the index falls back
        // to the default method rather than erroring.
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'gone');
        [, $controller] = $this->build($user, new FakeTwoFactorMethod(name: 'totp'));

        $response = $controller->index();

        self::assertSame(200, $response->getStatusCode());
    }

    public function testIndexEnabledWithCodeDeliveryShowsSendCodeStep(): void
    {
        // A code-delivery method that hasn't delivered a code yet shows the "send a code" pre-step.
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'email');
        [, $controller] = $this->build($user, new FakeTwoFactorMethod(name: 'email', codeDelivery: true));

        $response = $controller->index();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('user-two-factor-disable-send-code', (string) $response->getBody());
    }

    public function testIndexNotEnabledShowsMethodButtons(): void
    {
        $user = $this->createUser();
        $method = new FakeTwoFactorMethod(name: 'totp', buttonLabel: 'Authenticator app');
        [, $controller] = $this->build($user, $method);

        $response = $controller->index();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Authenticator app', (string) $response->getBody());
        self::assertStringContainsString('data-voyti-2fa-method="totp"', (string) $response->getBody());
    }

    public function testIndexNotEnabledWithNoAvailableMethodsShowsUnavailableNotice(): void
    {
        // Base package installed without any method package: the index degrades to a notice instead
        // of a 500 from getDefault().
        $user = $this->createUser();
        [, $controller] = $this->buildWith($user, []);

        $response = $controller->index();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('no authentication methods are installed', (string) $response->getBody());
    }

    public function testRegenerateBackupCodes(): void
    {
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'totp');
        $method = new FakeTwoFactorMethod(name: 'totp', verifyResult: true);
        [$container, $controller] = $this->build($user, $method);

        $response = $controller->regenerateBackupCodes($this->post(), '123456');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['code' => '123456'], $method->lastVerifyData);
        self::assertStringContainsString('Backup Codes', (string) $response->getBody());
        /** @var BackupCodeService $backupCodes */
        $backupCodes = $container->get(BackupCodeService::class);
        self::assertTrue($backupCodes->hasUnused($user));
    }

    public function testRegenerateBackupCodesFailureShowsError(): void
    {
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'totp');
        $method = new FakeTwoFactorMethod(name: 'totp', errorMessage: 'Nope.', verifyResult: false);
        [, $controller] = $this->build($user, $method);

        $response = $controller->regenerateBackupCodes($this->post(), 'wrong');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Nope.', (string) $response->getBody());
    }

    public function testRegenerateBackupCodesRedirectsWhenNotEnabled(): void
    {
        $user = $this->createUser();
        [, $controller] = $this->build($user, new FakeTwoFactorMethod(name: 'totp'));

        $response = $controller->regenerateBackupCodes($this->post(), '123456');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('//voyti/user-two-factor', $response->getHeaderLine('Location'));
    }

    public function testRegenerateBackupCodesViaPayloadForNonCodeBasedMethod(): void
    {
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'webauthn');
        $method = new FakeTwoFactorMethod(name: 'webauthn', codeBased: false, verifyResult: true);
        [$container, $controller] = $this->build($user, $method);

        $response = $controller->regenerateBackupCodes($this->postBody('{"assertion":"ok"}'), '');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['payload' => '{"assertion":"ok"}', 'domain' => ''], $method->lastVerifyData);
        self::assertStringContainsString('Backup Codes', (string) $response->getBody());
        /** @var BackupCodeService $backupCodes */
        $backupCodes = $container->get(BackupCodeService::class);
        self::assertTrue($backupCodes->hasUnused($user));
    }

    /**
     * @return array{0: ContainerInterface, 1: TwoFactorController}
     */
    private function build(User $user, FakeTwoFactorMethod $method): array
    {
        return $this->buildWith($user, [$method]);
    }

    /**
     * @param list<FakeTwoFactorMethod> $methods
     *
     * @return array{0: ContainerInterface, 1: TwoFactorController}
     */
    private function buildWith(User $user, array $methods): array
    {
        $container = $this->createTestContainer([
            CurrentUser::class => $this->createCurrentUser($user),
            TwoFactorMethodRegistry::class => new TwoFactorMethodRegistry($methods),
        ]);

        return [$container, $container->get(TwoFactorController::class)];
    }

    /**
     * A bare POST request for code-based flows, where the code is passed to the action directly and
     * the request body is unused.
     */
    private function post(): ServerRequestInterface
    {
        return new ServerRequest('POST', '/');
    }

    /**
     * A POST request carrying a raw body, standing in for a client-collected assertion payload
     * (WebAuthn) that non-code-based methods verify.
     */
    private function postBody(string $body): ServerRequestInterface
    {
        return (new ServerRequest('POST', '/'))->withBody(Stream::create($body));
    }
}
