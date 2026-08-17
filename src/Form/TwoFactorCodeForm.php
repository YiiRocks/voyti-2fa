<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\Form;

use Override;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\LabelsProviderInterface;
use Yiisoft\Validator\Rule\Integer;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;

/**
 * Backs the two-factor authentication code entry page for a given delivery `$method`.
 */
final class TwoFactorCodeForm extends FormModel implements LabelsProviderInterface
{
    #[Required]
    #[Integer]
    #[Length(exactly: 6)]
    public string $code = '';

    public function __construct(
        private readonly TranslatorInterface $translator,
        public readonly string $method,
    ) {}

    /**
     * @return string
     *
     * @psalm-return ''
     */
    #[Override]
    public function getFormName(): string
    {
        return '';
    }

    /**
     * @return string[]
     *
     * @psalm-return array{code: string}
     */
    #[Override]
    public function getPropertyLabels(): array
    {
        return [
            'code' => $this->translator->translate('voyti-2fa.view.two_factor.enter_code', category: 'voyti-2fa'),
        ];
    }

    #[Override]
    public function getValidationPropertyLabels(): array
    {
        return $this->getPropertyLabels();
    }
}
