<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\tests\Controller;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\TwoFactor\Controller\ConfirmController;
use YiiRocks\Voyti\TwoFactor\Service\BackupCodeService;
use YiiRocks\Voyti\TwoFactor\tests\Support\CurrentUserTrait;
use YiiRocks\Voyti\TwoFactor\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\TwoFactor\tests\Support\FakeTwoFactorMethod;
use YiiRocks\Voyti\TwoFactor\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\TwoFactor\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\TwoFactor\TwoFactorMethodRegistry;
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\CurrentUser;

final class ConfirmControllerTest extends TestCase
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

    public function testConfirmClientCollectedMethod(): void
    {
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'webauthn');

        // Failed payload verification re-renders the confirm screen.
        $failing = new FakeTwoFactorMethod(
            name: 'webauthn',
            codeBased: false,
            verifyResult: false,
            confirmFragmentUrl: '//voyti/webauthn/confirm-fragment',
        );
        [$container, $controller] = $this->build($failing, 'testuser');
        $response = $controller->confirm($this->postBody('{"assertion":"x"}'));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['payload' => '{"assertion":"x"}', 'domain' => ''], $failing->lastVerifyData);
        // Failed client-collected verification re-renders the confirm screen with the method's
        // fragment URL embedded in the page JS; no code form is rendered.
        $body = (string) $response->getBody();
        self::assertStringContainsString('confirm-fragment', $body);
        self::assertStringNotContainsString('one-time-code', $body);

        // Successful payload verification completes login.
        $passing = new FakeTwoFactorMethod(name: 'webauthn', codeBased: false, verifyResult: true);
        [$container, $controller] = $this->build($passing, 'testuser');
        $response = $controller->confirm($this->postBody('{"assertion":"ok"}'));
        self::assertSame(302, $response->getStatusCode());
        self::assertNull($container->get(SessionInterface::class)->get('credentials'));
    }

    public function testConfirmCodeVerificationFailureShowsError(): void
    {
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'totp');
        $method = new FakeTwoFactorMethod(name: 'totp', errorMessage: '', verifyResult: false);
        [$container, $controller] = $this->build($method, 'testuser');

        $response = $controller->confirm($this->postConfirm('000000'));

        $body = (string) $response->getBody();
        self::assertSame(200, $response->getStatusCode());
        // The error is bound to the code field, so it renders both in the summary and under the
        // field itself (two occurrences) - not only in the summary.
        self::assertSame(2, substr_count($body, 'Invalid verification code.'));
        // Failed confirmation leaves the pending credentials in place for a retry.
        self::assertNotNull($container->get(SessionInterface::class)->get('credentials'));
    }

    public function testConfirmCustomMethodErrorShown(): void
    {
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'totp');
        $method = new FakeTwoFactorMethod(name: 'totp', errorMessage: 'Custom TOTP error.', verifyResult: false);
        [, $controller] = $this->build($method, 'testuser');

        $response = $controller->confirm($this->postConfirm('000000'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Custom TOTP error.', (string) $response->getBody());
    }

    public function testConfirmInvalidFormRerenders(): void
    {
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'totp');
        [, $controller] = $this->build(new FakeTwoFactorMethod(name: 'totp'), 'testuser');

        $response = $controller->confirm($this->postConfirm(''));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testConfirmRedirectsToLoginWithoutStashedCredentials(): void
    {
        [, $controller] = $this->build(new FakeTwoFactorMethod(name: 'totp'), stashLogin: null);

        $response = $controller->confirm(new ServerRequest('GET', '/'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('//voyti/session-login', $response->getHeaderLine('Location'));
    }

    public function testConfirmResolvesStoredMethodOverDefault(): void
    {
        // Default method rejects the code; the user's stored (non-default) method accepts it. Login
        // must complete, proving the stored method - not the default - was used.
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'extra');
        [$container, $controller] = $this->buildWith([
            new FakeTwoFactorMethod(name: 'totp', verifyResult: false),
            new FakeTwoFactorMethod(name: 'extra', verifyResult: true),
        ], 'testuser');

        $response = $controller->confirm($this->postConfirm('123456'));

        self::assertSame(302, $response->getStatusCode());
        self::assertNull($container->get(SessionInterface::class)->get('credentials'));
    }

    public function testConfirmSuccessViaBackupCode(): void
    {
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'totp');
        $method = new FakeTwoFactorMethod(name: 'totp', verifyResult: false);
        [$container, $controller] = $this->build($method, 'testuser');

        /** @var BackupCodeService $backupCodes */
        $backupCodes = $container->get(BackupCodeService::class);
        $codes = $backupCodes->generate($user);

        $response = $controller->confirm($this->postConfirm($codes[0]));

        self::assertSame(302, $response->getStatusCode());
        self::assertNull($container->get(SessionInterface::class)->get('credentials'));
    }

    public function testConfirmSuccessViaMethodCode(): void
    {
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'totp');
        $method = new FakeTwoFactorMethod(name: 'totp', verifyResult: true);
        [$container, $controller] = $this->build($method, 'testuser', rememberMe: true);

        $response = $controller->confirm($this->postConfirm('123456'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame(['code' => '123456'], $method->lastVerifyData);
        self::assertNull($container->get(SessionInterface::class)->get('credentials'));
        // rememberMe stashed as true issues the autoLogin cookie.
        self::assertStringContainsString('autoLogin', $response->getHeaderLine('Set-Cookie'));
    }

    public function testConfirmUnknownLoginRendersDefaultMethod(): void
    {
        [, $controller] = $this->build(new FakeTwoFactorMethod(name: 'totp'), 'ghost');

        $response = $controller->confirm($this->postConfirm('123456'));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testConfirmWithoutStashedRememberMeIssuesNoCookie(): void
    {
        $user = $this->createUser();
        $this->createUserTwoFactor((int) ($user->getId() ?? 0), method: 'totp');
        [$container, $controller] = $this->build(new FakeTwoFactorMethod(name: 'totp', verifyResult: true), stashLogin: null);
        // Stash credentials with no rememberMe key: the default must be "do not remember".
        $container->get(SessionInterface::class)->set('credentials', ['login' => 'testuser']);

        $response = $controller->confirm($this->postConfirm('123456'));

        self::assertSame(302, $response->getStatusCode());
        self::assertStringNotContainsString('autoLogin', $response->getHeaderLine('Set-Cookie'));
    }

    /**
     * @return array{0: ContainerInterface, 1: ConfirmController}
     */
    private function build(FakeTwoFactorMethod $method, ?string $stashLogin, bool $rememberMe = false): array
    {
        return $this->buildWith([$method], $stashLogin, $rememberMe);
    }

    /**
     * @param list<FakeTwoFactorMethod> $methods
     *
     * @return array{0: ContainerInterface, 1: ConfirmController}
     */
    private function buildWith(array $methods, ?string $stashLogin, bool $rememberMe = false): array
    {
        $container = $this->createTestContainer([
            CurrentUser::class => $this->createCurrentUser(),
            TwoFactorMethodRegistry::class => new TwoFactorMethodRegistry($methods),
        ]);

        if ($stashLogin !== null) {
            $container->get(SessionInterface::class)->set('credentials', [
                'login' => $stashLogin,
                'rememberMe' => $rememberMe,
            ]);
        }

        return [$container, $container->get(ConfirmController::class)];
    }

    private function postBody(string $body): ServerRequestInterface
    {
        return (new ServerRequest('POST', '/'))->withBody(Stream::create($body));
    }

    private function postConfirm(string $code): ServerRequestInterface
    {
        return (new ServerRequest('POST', '/'))->withParsedBody(['confirm' => ['twoFactorAuthenticationCode' => $code]]);
    }
}
