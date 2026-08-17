<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\tests\Form;

use YiiRocks\Voyti\TwoFactor\Form\ConfirmForm;
use YiiRocks\Voyti\TwoFactor\Form\TwoFactorCodeForm;
use YiiRocks\Voyti\TwoFactor\tests\TestCase;

final class TwoFactorFormTest extends TestCase
{
    public function testConfirmForm(): void
    {
        $form = new ConfirmForm($this->createTranslator());

        self::assertSame('confirm', $form->getFormName());
        self::assertSame(
            ['twoFactorAuthenticationCode' => 'Authentication Code'],
            $form->getPropertyLabels(),
        );
        self::assertSame($form->getPropertyLabels(), $form->getValidationPropertyLabels());
    }

    public function testTwoFactorCodeForm(): void
    {
        $form = new TwoFactorCodeForm($this->createTranslator(), 'totp');

        self::assertSame('', $form->getFormName());
        self::assertSame('totp', $form->method);
        self::assertSame(['code' => 'Enter the verification code'], $form->getPropertyLabels());
        self::assertSame($form->getPropertyLabels(), $form->getValidationPropertyLabels());
    }
}
