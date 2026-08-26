<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\tests\Support;

use Composer\InstalledVersions;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Cookies\CookieEncryptor;
use Yiisoft\Cookies\CookieSigner;
use Yiisoft\Csrf\CsrfTokenInterface;
use Yiisoft\Csrf\StubCsrfToken;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\Flash;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\Message\Php\MessageSource;
use Yiisoft\Translator\SimpleMessageFormatter;
use Yiisoft\Translator\Translator;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;
use Yiisoft\Widget\WidgetFactory;
use Yiisoft\Yii\View\Renderer\InjectionContainer\InjectionContainer;
use Yiisoft\Yii\View\Renderer\InjectionContainer\InjectionContainerInterface;

/**
 * Builds a fresh PSR-11 DI container per test by merging the core module's `config/di.php` with this
 * package's own, plus hydrator/validator, with in-memory test fakes overlaid. Per-test overrides
 * (typically a {@see TwoFactorMethodRegistry} carrying a {@see FakeTwoFactorMethod}) are merged on top.
 */
trait TestContainerTrait
{
    /**
     * @param array<class-string|string, object|class-string|callable> $overrides
     */
    protected function createTestContainer(array $overrides = []): ContainerInterface
    {
        $corePath = InstalledVersions::getInstallPath('yiirocks/voyti');
        $packagePath = dirname(__DIR__, 2);

        $params = array_merge(
            require $corePath . '/config/params.php',
            require $packagePath . '/config/params.php',
        );

        // Replicate yiisoft/config's auto-merge of the views package's viewsPackagePaths param.
        $viewsPackageInstallPath = InstalledVersions::getInstallPath('yiirocks/voyti-views-bootstrap5');
        $viewsPackageParams = require $viewsPackageInstallPath . '/config/params.php';
        $params['yiirocks/voyti'] = array_merge($params['yiirocks/voyti'], $viewsPackageParams['yiirocks/voyti']);

        $definitions = (static fn(array $params): array => require $corePath . '/config/di.php')($params);
        $definitions = array_merge(
            $definitions,
            (static fn(array $params): array => require $packagePath . '/config/di.php')($params),
        );

        $hydratorDiPath = InstalledVersions::getInstallPath('yiisoft/hydrator') . '/config/di.php';
        $definitions = array_merge(require $hydratorDiPath, $definitions);

        // Voyti binds no ValidatorInterface of its own - it comes from yiisoft/validator's own
        // config/di.php, auto-merged by yiisoft/config in a real host. Replicate that merge so
        // FormHydrator's real validation runs instead of being hand-mocked per test.
        $validatorInstallPath = InstalledVersions::getInstallPath('yiisoft/validator');
        $validatorParams = require $validatorInstallPath . '/config/params.php';
        $validatorDiPath = $validatorInstallPath . '/config/di.php';
        $validatorDefinitions = (static fn(array $params): array => require $validatorDiPath)(
            array_merge($params, $validatorParams),
        );
        /** @var CategorySource $validatorCategorySource */
        $validatorCategorySource = $validatorDefinitions['yii.validator.categorySource']['definition']();
        $definitions = array_merge($validatorDefinitions, $definitions);

        $psr17Factory = new Psr17Factory();
        $session = new FakeSession();

        $definitions = array_merge($definitions, [
            Aliases::class => new Aliases(),
            CookieEncryptor::class => new CookieEncryptor('test-secret-key-0123456789abcdef'),
            CookieSigner::class => new CookieSigner('test-secret-key-0123456789abcdef'),
            CsrfTokenInterface::class => new StubCsrfToken('test-csrf-token'),
            CurrentRoute::class => new CurrentRoute(),
            EventDispatcherInterface::class => new EventCaptureDispatcher(),
            FlashInterface::class => new Flash($session),
            InjectionContainerInterface::class => InjectionContainer::class,
            LoggerInterface::class => new NullLogger(),
            RequestFactoryInterface::class => $psr17Factory,
            ResponseFactoryInterface::class => $psr17Factory,
            SessionInterface::class => $session,
            StreamFactoryInterface::class => $psr17Factory,
            TranslatorInterface::class => (static function () use ($corePath, $packagePath, $validatorCategorySource): TranslatorInterface {
                $translator = new Translator('en', null, 'voyti');
                $translator->addCategorySources(
                    new CategorySource(
                        'voyti',
                        new MessageSource($corePath . '/resources/messages'),
                        new SimpleMessageFormatter(),
                    ),
                    new CategorySource(
                        'voyti-2fa',
                        new MessageSource($packagePath . '/resources/messages'),
                        new SimpleMessageFormatter(),
                    ),
                    $validatorCategorySource,
                );

                return $translator;
            })(),
            UrlGeneratorInterface::class => new FakeUrlGenerator(),
            WebView::class => new WebView(),
        ]);

        $definitions = array_merge($definitions, $overrides);

        $container = new Container(ContainerConfig::create()->withDefinitions($definitions));
        WidgetFactory::initialize($container);

        return $container;
    }
}
