<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\Console;

use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YiiRocks\Voyti\Console\UserLookupTrait;
use YiiRocks\Voyti\TwoFactor\Model\UserTwoFactor;
use YiiRocks\Voyti\TwoFactor\Service\TwoFactorDisableService;
use Yiisoft\Yii\Console\ExitCode;

/**
 * Console command (`voyti:2fa:disable`) that disables two-factor authentication for a user, looked
 * up via {@see UserLookupTrait}, bypassing the re-authentication step required through the web UI.
 */
final class DisableTwoFactorCommand extends Command
{
    use UserLookupTrait;

    public function __construct(
        private readonly TwoFactorDisableService $twoFactorDisableService,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->setName('voyti:2fa:disable')
            ->setDescription('Disable two-factor authentication for a user');
        $this->configureUserOptions();
    }

    /**
     * @return 0|64|67
     */
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $user = $this->findUserFromInput($input, $output, 'voyti:2fa:disable');
        if ($user === null) {
            return $this->getLookupFailureExitCode();
        }

        if (!UserTwoFactor::forUser($user)->isEnabled()) {
            $output->writeln('<info>Two-factor authentication is not enabled for this user.</info>');
            return ExitCode::OK;
        }

        $this->twoFactorDisableService->disable($user);

        $output->writeln('<info>Two-factor authentication disabled.</info>');
        return ExitCode::OK;
    }
}
