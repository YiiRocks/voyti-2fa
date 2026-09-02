<?php

declare(strict_types=1);

use YiiRocks\Voyti\TwoFactor\Console\DisableTwoFactorCommand;

return [
    'yiirocks/voyti' => [
        // Contribute the "Two-Factor" link to core's account-settings menu. Core reads this under its
        // own param key and merges it in (same cross-package pattern as 2fa.methodRoutes), so it
        // needs no knowledge of this package.
        'accountMenuItems' => [
            [
                'label' => 'voyti-2fa.menu.two_factor',
                'category' => 'voyti-2fa',
                'route' => 'voyti/user-two-factor',
            ],
        ],

        '2fa' => [
            // Permissions whose holders must have 2FA enabled; enforced by
            // TwoFactorAuthenticationEnforceMiddleware once the host adds it to its pipeline.
            'forcedPermissions' => [],
        ],
    ],

    'yiisoft/yii-console' => [
        'commands' => [
            'voyti:2fa:disable' => DisableTwoFactorCommand::class,
        ],
    ],
];
