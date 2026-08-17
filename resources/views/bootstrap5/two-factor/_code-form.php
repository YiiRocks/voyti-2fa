<?php

declare(strict_types=1);

use YiiRocks\Voyti\TwoFactor\Form\TwoFactorCodeForm;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;
use Yiisoft\Yii\View\Renderer\Csrf;

/**
 * @var WebView $this
 * @var TwoFactorCodeForm $form
 * @var string $formSubmitUrl
 * @var TranslatorInterface $translator
 * @var Csrf $csrf
 */

echo Html::form()
    ->post($formSubmitUrl)
    ->csrf($csrf)
    ->open();

$tabindex = 0;

echo Field::text($form, 'code')->addInputAttributes(['inputmode' => 'numeric'])->tabIndex(++$tabindex);

echo Field::buttonGroup()
    ->buttonsData([
        [$translator->translate('voyti.view.reset_button', category: 'voyti'), 'type' => 'reset', 'tabindex' => $tabindex + 2],
        [$translator->translate('voyti-2fa.view.two_factor.enable', category: 'voyti-2fa'), 'type' => 'submit', 'tabindex' => ++$tabindex],
    ]);

echo Html::form()->close();
