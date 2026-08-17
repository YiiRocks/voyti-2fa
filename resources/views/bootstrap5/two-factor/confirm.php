<?php

declare(strict_types=1);

use YiiRocks\Voyti\TwoFactor\Form\ConfirmForm;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Json\Json;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;
use Yiisoft\Yii\View\Renderer\Csrf;

/**
 * @var WebView $this
 * @var ConfirmForm $form
 * @var array{
 *     isCodeBased: bool,
 *     methodFragmentUrl: string|null,
 *     formSubmitUrl: string,
 * } $data
 * @var TranslatorInterface $translator
 * @var Csrf $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti-2fa.view.two_factor.title', category: 'voyti-2fa'));

echo Html::div()->open();
echo Html::H1($translator->translate('voyti-2fa.view.two_factor.title', category: 'voyti-2fa'));

if ($data['isCodeBased']) {
    echo Html::form()
        ->post($data['formSubmitUrl'])
        ->csrf($csrf)
        ->open();

    echo Field::errorSummary($form);

    echo Html::p($translator->translate('voyti-2fa.view.two_factor.backup_code_hint', category: 'voyti-2fa'))->class('text-muted small');

    $tabindex = 0;

    echo Field::text($form, 'twoFactorAuthenticationCode')->addInputAttributes(['autocomplete' => 'one-time-code'])->tabIndex(++$tabindex);

    echo Field::buttonGroup()
        ->buttonsData([
            [$translator->translate('voyti.view.reset_button', category: 'voyti'), 'type' => 'reset', 'tabindex' => $tabindex + 2],
            [$translator->translate('voyti-2fa.view.two_factor.verify_button', category: 'voyti-2fa'), 'type' => 'submit', 'tabindex' => ++$tabindex],
        ]);

    echo Html::form()->close();
} else {
    echo Html::div()->id('voyti-session-confirm-method')->attribute('data-voyti-2fa-assertion-host', '')->open();
    echo Html::div()->class('d-flex justify-content-center')->open();
    echo Html::div()
        ->class('spinner-border')
        ->attribute('role', 'status')
        ->content(Html::span($translator->translate('voyti-2fa.view.two_factor.loading', category: 'voyti-2fa'))->class('visually-hidden'));
    echo Html::div()->close();
    echo Html::div()->close();

    $confirmConfig = [
        'fragmentUrl' => $data['methodFragmentUrl'],
    ];
    $confirmConfigJson = Json::htmlEncode($confirmConfig);

    $js = <<<JS
        (() => {
            const cfg = {$confirmConfigJson};

            const container = document.getElementById('voyti-session-confirm-method');
            if (!container || !cfg.fragmentUrl) {
                return;
            }

            fetch(cfg.fragmentUrl, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error();
                    }
                    return response.text();
                })
                .then(html => {
                    container.innerHTML = html;

                    // Scripts inserted via innerHTML don't execute, so re-inject them manually -
                    // the method fragment (e.g. WebAuthn) runs its ceremony from this script.
                    container.querySelectorAll('script').forEach(script => {
                        const clone = document.createElement('script');
                        Array.from(script.attributes).forEach(attr => clone.setAttribute(attr.name, attr.value));
                        clone.textContent = script.textContent;
                        script.replaceWith(clone);
                    });
                })
                .catch(() => {
                    location.href = cfg.fragmentUrl;
                });
        })();
        JS;

    echo Html::script($js)->render();
}
echo Html::div()->close();
