<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\Form;

use Override;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\LabelsProviderInterface;
use Yiisoft\Validator\Rule\Required;

/**
 * Backs the two-factor login confirmation form: a single code field accepting either a method code
 * (TOTP/email) or an alphanumeric backup code, so no format-specific rule beyond presence applies.
 */
final class ConfirmForm extends FormModel implements LabelsProviderInterface
{
    #[Required]
    public string $twoFactorAuthenticationCode = '';

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    /**
     * @return 'confirm'
     */
    #[Override]
    public function getFormName(): string
    {
        return 'confirm';
    }

    /**
     * @return array{twoFactorAuthenticationCode: string}
     */
    #[Override]
    public function getPropertyLabels(): array
    {
        return [
            'twoFactorAuthenticationCode' => $this->translator->translate('voyti-2fa.view.two_factor.code_label', category: 'voyti-2fa'),
        ];
    }

    #[Override]
    public function getValidationPropertyLabels(): array
    {
        return $this->getPropertyLabels();
    }
}
