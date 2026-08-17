<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\tests\Middleware;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionProperty;
use YiiRocks\Voyti\Helper\FlashType;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\TwoFactor\Middleware\TwoFactorAuthenticationEnforceMiddleware;
use YiiRocks\Voyti\TwoFactor\tests\Support\CurrentRouteTrait;
use YiiRocks\Voyti\TwoFactor\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\TwoFactor\tests\Support\FakeTwoFactorMethod;
use YiiRocks\Voyti\TwoFactor\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\TwoFactor\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\TwoFactor\tests\TestCase;
use YiiRocks\Voyti\TwoFactor\TwoFactorMethodRegistry;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Permission;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\User\CurrentUser;

#[AllowMockObjectsWithoutExpectations]
final class TwoFactorAuthenticationEnforceMiddlewareTest extends TestCase
{
    use CurrentRouteTrait;
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

    public static function exemptRouteProvider(): iterable
    {
        yield 'logout' => ['voyti/session-logout'];
        yield 'two factor' => ['voyti/user-two-factor-enable'];
    }

    public function testProcessDoesNotQueryRbacWhenNoForcedPermissions(): void
    {
        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::never())->method('getPermissionsByUserId');

        $request = new ServerRequest('GET', '/');
        $response = $this->createStub(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->middleware(authManager: $authManager, forcedPermissions: []);

        self::assertSame($response, $middleware->process($request, $handler));
    }

    #[DataProvider('exemptRouteProvider')]
    public function testProcessPassesThroughForExemptRoute(string $routeName): void
    {
        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::never())->method('getPermissionsByUserId');

        $request = new ServerRequest('GET', '/');
        $response = $this->createStub(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->middleware(
            authManager: $authManager,
            currentRoute: $this->createCurrentRoute($routeName),
            forcedPermissions: ['admin'],
        );

        self::assertSame($response, $middleware->process($request, $handler));
    }

    public function testProcessPassesThroughForNonUserIdentity(): void
    {
        $authManager = $this->createStub(ManagerInterface::class);
        $authManager->method('getPermissionsByUserId')->willReturn(['admin' => new Permission('admin')]);

        $request = new ServerRequest('GET', '/');
        $response = $this->createStub(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->middleware(
            currentUser: $this->currentUser($this->createStub(IdentityInterface::class)),
            authManager: $authManager,
            forcedPermissions: ['admin'],
        );

        self::assertSame($response, $middleware->process($request, $handler));
    }

    public function testProcessPassesThroughWhenNoMethodRegistered(): void
    {
        $authManager = $this->createStub(ManagerInterface::class);
        $authManager->method('getPermissionsByUserId')->willReturn(['admin' => new Permission('admin')]);

        $request = new ServerRequest('GET', '/');
        $response = $this->createStub(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        // No method package installed: 2FA is inactive, so enforcement passes through even for a
        // forced-permission holder without 2FA.
        $middleware = $this->middleware(
            authManager: $authManager,
            forcedPermissions: ['admin'],
            methods: [],
        );

        self::assertSame($response, $middleware->process($request, $handler));
    }

    public function testProcessPassesThroughWhenUserHasRequiredPermissionAnd2FAEnabled(): void
    {
        $this->createUserTwoFactor(42, method: 'totp');

        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::once())->method('getPermissionsByUserId')->with(42)
            ->willReturn(['admin' => new Permission('admin')]);

        $request = new ServerRequest('GET', '/');
        $response = $this->createStub(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->middleware(authManager: $authManager, forcedPermissions: ['admin']);

        self::assertSame($response, $middleware->process($request, $handler));
    }

    public function testProcessPassesThroughWhenUserLacksRequiredPermissions(): void
    {
        $authManager = $this->createStub(ManagerInterface::class);
        $authManager->method('getPermissionsByUserId')->willReturn(['editor' => new Permission('editor')]);

        $request = new ServerRequest('GET', '/');
        $response = $this->createStub(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->middleware(authManager: $authManager, forcedPermissions: ['admin']);

        self::assertSame($response, $middleware->process($request, $handler));
    }

    public function testProcessRedirectsWhenUserHasRequiredPermissionBut2FANotEnabled(): void
    {
        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::once())->method('getPermissionsByUserId')->with(42)
            ->willReturn(['admin' => new Permission('admin')]);

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::once())->method('set')->with(
            FlashType::WARNING,
            'Two-factor authentication is required for your account. Please enable it to continue.',
        );

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $middleware = $this->middleware(
            authManager: $authManager,
            currentRoute: $this->createCurrentRoute('voyti/admin'),
            forcedPermissions: ['admin'],
            flash: $flash,
        );

        $response = $middleware->process(new ServerRequest('GET', '/'), $handler);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('//voyti/user-two-factor', $response->getHeaderLine('Location'));
    }

    public function testProcessRedirectsWithoutFlashServiceConfigured(): void
    {
        $authManager = $this->createStub(ManagerInterface::class);
        $authManager->method('getPermissionsByUserId')->willReturn(['admin' => new Permission('admin')]);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        // No flash service wired: the redirect must still happen, the flash call simply skipped.
        $middleware = $this->middleware(
            authManager: $authManager,
            currentRoute: $this->createCurrentRoute('voyti/admin'),
            forcedPermissions: ['admin'],
            flash: null,
        );

        $response = $middleware->process(new ServerRequest('GET', '/'), $handler);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('//voyti/user-two-factor', $response->getHeaderLine('Location'));
    }

    public function testProcessWithUserIdZeroQueriesPermissionsForZero(): void
    {
        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::once())->method('getPermissionsByUserId')->with(0)->willReturn([]);

        $request = new ServerRequest('GET', '/');
        $response = $this->createStub(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = $this->middleware(
            currentUser: $this->currentUser($this->userWithId(null)),
            authManager: $authManager,
            forcedPermissions: ['admin'],
        );

        self::assertSame($response, $middleware->process($request, $handler));
    }

    private function currentUser(IdentityInterface $identity): CurrentUser
    {
        $currentUser = new CurrentUser(
            $this->createStub(IdentityRepositoryInterface::class),
            $this->createStub(EventDispatcherInterface::class),
        );
        $currentUser->overrideIdentity($identity);

        return $currentUser;
    }

    /**
     * @param list<FakeTwoFactorMethod> $methods
     * @param list<string> $forcedPermissions
     */
    private function middleware(
        ?CurrentUser $currentUser = null,
        ?ManagerInterface $authManager = null,
        ?CurrentRoute $currentRoute = null,
        array $methods = [new FakeTwoFactorMethod(name: 'totp')],
        array $forcedPermissions = [],
        ?FlashInterface $flash = null,
    ): TwoFactorAuthenticationEnforceMiddleware {
        return new TwoFactorAuthenticationEnforceMiddleware(
            $currentUser ?? $this->currentUser($this->userWithId(42)),
            $authManager ?? $this->createStub(ManagerInterface::class),
            $currentRoute ?? $this->createCurrentRoute('voyti/admin'),
            new Psr17Factory(),
            $this->createTranslator(),
            new FakeUrlGenerator(),
            new TwoFactorMethodRegistry($methods),
            $forcedPermissions,
            $flash,
        );
    }

    private function userWithId(?int $id): User
    {
        $user = new User();
        if ($id !== null) {
            (new ReflectionProperty(User::class, 'id'))->setValue($user, $id);
        }

        return $user;
    }
}
