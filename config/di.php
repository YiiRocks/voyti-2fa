<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use YiiRocks\Voyti\TwoFactor\Auth\TwoFactorLoginChallenge;
use YiiRocks\Voyti\TwoFactor\Middleware\TwoFactorAuthenticationEnforceMiddleware;
use YiiRocks\Voyti\TwoFactor\Service\BackupCodeService;
use YiiRocks\Voyti\TwoFactor\Service\TwoFactorDisableService;
use YiiRocks\Voyti\TwoFactor\TwoFactorMethodInterface;
use YiiRocks\Voyti\TwoFactor\TwoFactorMethodRegistry;
use Yiisoft\Di\Reference\TagReference;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\Message\Php\MessageSource;
use Yiisoft\Translator\SimpleMessageFormatter;

/** @var array $params */

return [
    // Backup codes for account recovery.
    BackupCodeService::class => static fn(): BackupCodeService => new BackupCodeService(
        new PasswordHasher(PASSWORD_BCRYPT, ['cost' => 6]),
    ),

    // Shared disable logic used by both the settings controller and the `voyti:2fa:disable` command.
    TwoFactorDisableService::class => TwoFactorDisableService::class,

    // Every method package tags its provider `voyti.two-factor-method`; the registry collects them.
    // Wrapped in a generator (instead of `Reference::to('tag@...')`) so the tag is only resolved
    // once a method is actually looked up, not merely when the registry is constructed.
    TwoFactorMethodRegistry::class => static fn(ContainerInterface $container): TwoFactorMethodRegistry
        => new TwoFactorMethodRegistry((static function () use ($container): iterable {
            /** @var iterable<TwoFactorMethodInterface> $methods */
            $methods = $container->get(TagReference::id('voyti.two-factor-method'));
            yield from $methods;
        })()),

    // Hook the 2FA confirmation step into the core login flow via the login-challenge seam.
    TwoFactorLoginChallenge::class => [
        'class' => TwoFactorLoginChallenge::class,
        'tags' => ['voyti.login-challenge'],
    ],

    // Tagged `voyti.enforce-middleware` so it auto-joins core's VoytiMiddleware chain once this
    // package is installed - no host wiring needed.
    TwoFactorAuthenticationEnforceMiddleware::class => [
        'class' => TwoFactorAuthenticationEnforceMiddleware::class,
        '__construct()' => [
            'forcedPermissions' => $params['yiirocks/voyti']['2fa']['forcedPermissions'] ?? [],
        ],
        'tags' => ['voyti.enforce-middleware'],
    ],

    // Translation category source for this package's message files.
    'yiirocks/voyti-2fa.translator' => [
        'definition' => static fn(): CategorySource => new CategorySource(
            'voyti-2fa',
            new MessageSource(dirname(__DIR__) . '/resources/messages'),
            new SimpleMessageFormatter(),
        ),
        'tags' => ['translation.categorySource'],
    ],
];
