<?php

declare(strict_types=1);

use YiiRocks\Voyti\Middleware\RequireLoginMiddleware;
use YiiRocks\Voyti\TwoFactor\Controller\ConfirmController;
use YiiRocks\Voyti\TwoFactor\Controller\TwoFactorController;
use YiiRocks\Voyti\VoytiRoutes;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

/** @var array $params */

$voytiParams = $params['yiirocks/voyti'] ?? [];

$settingsRoutes = [
    Route::methods(['GET', 'POST'], 'two-factor/')->name('voyti/user-two-factor')->action([TwoFactorController::class, 'index']),
    Route::post('two-factor/enable')->name('voyti/user-two-factor-enable')->action([TwoFactorController::class, 'enable']),
    Route::post('two-factor/disable/')->name('voyti/user-two-factor-disable')->action([TwoFactorController::class, 'disable']),
    Route::post('two-factor/disable/send-code')->name('voyti/user-two-factor-disable-send-code')->action([TwoFactorController::class, 'disableSendCode']),
    Route::post('two-factor/backup-codes/regenerate')->name('voyti/user-two-factor-regenerate-backup-codes')->action([TwoFactorController::class, 'regenerateBackupCodes']),
    Route::get('two-factor/backup-codes')->name('voyti/user-two-factor-backup-codes')->action([TwoFactorController::class, 'showBackupCodes']),
];

// Each installed method package (e.g. yiirocks/voyti-2fa-email) contributes its own setup route(s)
// via the twoFactorMethodRoutes param; they land inside the settings/ group with the same middleware.
foreach ($voytiParams['twoFactorMethodRoutes'] ?? [] as $route) {
    $settingsRoutes[] = $route;
}

return [
    Group::create()
        ->middleware(...VoytiRoutes::webMiddleware($voytiParams))
        ->routes(
            // Login-confirmation step, reached mid-login (guest-accessible) before the session is set.
            Route::methods(['GET', 'POST'], 'confirm')->name('voyti/session-confirm')->action([ConfirmController::class, 'confirm']),
            Group::create('settings/')
                ->middleware(RequireLoginMiddleware::class)
                ->routes(...$settingsRoutes),
        ),
];
