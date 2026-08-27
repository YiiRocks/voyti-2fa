<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\Service;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\TwoFactor\Console\DisableTwoFactorCommand;
use YiiRocks\Voyti\TwoFactor\Controller\TwoFactorController;
use YiiRocks\Voyti\TwoFactor\Model\UserTwoFactor;
use YiiRocks\Voyti\TwoFactor\TwoFactorMethodInterface;
use YiiRocks\Voyti\TwoFactor\TwoFactorMethodRegistry;

/**
 * Turns off two-factor authentication for a user: runs the active method's {@see
 * TwoFactorMethodInterface::onDisable()} hook, clears the stored {@see UserTwoFactor} state, and
 * wipes backup codes. Shared by {@see TwoFactorController} (after
 * re-authentication) and {@see DisableTwoFactorCommand} (admin
 * bypass, no re-authentication).
 */
final readonly class TwoFactorDisableService
{
    public function __construct(
        private TwoFactorMethodRegistry $twoFactorMethods,
        private BackupCodeService $backupCodeService,
    ) {}

    public function disable(User $user): void
    {
        $twoFactor = UserTwoFactor::forUser($user);

        $this->resolveMethod($twoFactor->getMethod())->onDisable($user);

        $twoFactor->setEnabled(false);
        $twoFactor->setSecret(null);
        $twoFactor->setMethod(null);
        $twoFactor->save();

        $this->backupCodeService->clear($user);
    }

    /**
     * Falls back to the default method when the stored type is null or no longer registered
     * (e.g. a host removed a method package while a user still has that type enabled).
     */
    private function resolveMethod(?string $name): TwoFactorMethodInterface
    {
        return $this->twoFactorMethods->has($name)
            ? $this->twoFactorMethods->get((string) $name)
            : $this->twoFactorMethods->getDefault();
    }
}
