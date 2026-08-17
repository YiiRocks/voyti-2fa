<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\tests\Support;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\TwoFactor\TwoFactorMethodInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Configurable test double for {@see TwoFactorMethodInterface}: plain public properties instead of
 * constructor collaborators, so view-helper/controller tests can model any method shape (code-based,
 * client-collected, unavailable, ...) without resolving a real provider.
 */
final class FakeTwoFactorMethod implements TwoFactorMethodInterface
{
    public function __construct(
        public string $name = 'fake',
        public bool $available = true,
        public bool $codeBased = true,
        public bool $codeDelivery = false,
        public ?string $confirmFragmentUrl = null,
        public ?string $reauthFragmentUrl = null,
        public ?string $settingsUrl = '//voyti/fake-settings',
        public string $buttonLabel = 'Fake',
        public string $enabledWithMethodName = 'Fake method',
        public string $errorMessage = '',
        public bool $verifyResult = true,
        public array $lastVerifyData = [],
        public bool $onDisableCalled = false,
        public bool $onAuthenticationStepStartCalled = false,
    ) {}

    public function getButtonLabel(TranslatorInterface $translator): string
    {
        return $this->buttonLabel;
    }

    public function getConfirmFragmentUrl(UrlGeneratorInterface $url): ?string
    {
        return $this->confirmFragmentUrl;
    }

    public function getEnabledWithMethodName(TranslatorInterface $translator): string
    {
        return $this->enabledWithMethodName;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getReauthFragmentUrl(UrlGeneratorInterface $url): ?string
    {
        return $this->reauthFragmentUrl;
    }

    public function getSettingsUrl(UrlGeneratorInterface $url): string
    {
        return $this->settingsUrl ?? $url->generate('voyti/user-two-factor-' . $this->name);
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function isCodeBased(): bool
    {
        return $this->codeBased;
    }

    public function onAuthenticationStepStart(User $user): void
    {
        $this->onAuthenticationStepStartCalled = true;
    }

    public function onDisable(User $user): void
    {
        $this->onDisableCalled = true;
    }

    public function requiresCodeDelivery(): bool
    {
        return $this->codeDelivery;
    }

    public function verify(User $user, array $data): bool
    {
        $this->lastVerifyData = $data;

        return $this->verifyResult;
    }
}
