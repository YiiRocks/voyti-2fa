<?php

declare(strict_types=1);

use YiiRocks\Voyti\Helper\LinkButtonHelper;
use YiiRocks\Voyti\TwoFactor\Form\TwoFactorCodeForm;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Json\Json;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;
use Yiisoft\Yii\View\Renderer\Csrf;

/**
 * @var WebView $this
 * @var TwoFactorCodeForm $form
 * @var array{
 *     menu: list<array{label: string, url: string, alignEnd: bool, routeName: string|null}>,
 *     errors: array<string, list<string>>,
 *     isEnabled: bool,
 *     method: string,
 *     enabledWithMethodMessage: string,
 *     codeDelivered: bool,
 *     requiresCodeDelivery: bool,
 *     isCodeBased: bool,
 *     reauthFragmentUrl: string|null,
 *     disableSendCodeUrl: string,
 *     disableUrl: string,
 *     hasBackupCodes: bool,
 *     regenerateBackupCodesUrl: string,
 *     methods: list<array{name: string, label: string}>,
 *     methodUrls: array<string, string>,
 *     preloadedFragmentHtml: string|null,
 *     autoloadUrl: string|null,
 * } $data
 * @var string $coreViews absolute base path of the core module's shared views
 * @var TranslatorInterface $translator
 * @var array{success: string|null, warning: string|null} $flash
 * @var Csrf $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti-2fa.view.two_factor.title', category: 'voyti-2fa'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render($coreViews . '/shared/_menu', ['menu' => $data['menu']]);
/** @psalm-suppress InvalidScope */
echo $this->render($coreViews . '/shared/_flash');

echo Html::H1($translator->translate('voyti-2fa.view.two_factor.title', category: 'voyti-2fa'));

if (!empty($data['errors'])) {
    echo Html::div()->class('alert alert-danger')->open();
    foreach ($data['errors'] as $fieldErrors) {
        foreach ($fieldErrors as $error) {
            echo Html::div($error);
        }
    }
    echo Html::div()->close();
}

if ($data['isEnabled']) {
    echo Html::p($data['enabledWithMethodMessage']);

    if ($data['requiresCodeDelivery'] && !$data['codeDelivered']) {
        echo Html::div()->class('alert alert-info')->open();
        echo $translator->translate('voyti-2fa.view.two_factor.disable_confirm_intro', category: 'voyti-2fa');
        echo Html::div()->close();

        echo Html::form()
            ->post($data['disableSendCodeUrl'])
            ->csrf($csrf)
            ->open();

        echo Field::buttonGroup()
            ->buttonsData([
                [$translator->translate('voyti-2fa.view.two_factor.disable_send_code', category: 'voyti-2fa'), 'type' => 'submit', 'class' => 'btn btn-danger', 'tabindex' => 1],
            ]);

        echo Html::form()->close();
    } elseif ($data['isCodeBased']) {
        if ($data['requiresCodeDelivery']) {
            echo Html::div()->class('alert alert-info')->open();
            echo $translator->translate('voyti-2fa.view.two_factor.enter_code', category: 'voyti-2fa');
            echo Html::div()->close();
        }

        echo Html::form()
            ->post($data['disableUrl'])
            ->csrf($csrf)
            ->open();

        echo Html::p($translator->translate('voyti-2fa.view.two_factor.backup_code_hint', category: 'voyti-2fa'))->class('text-muted small');

        echo Field::text($form, 'code')->inputId('voyti-2fa-disable-code')->tabIndex(1);

        echo Field::buttonGroup()
            ->buttonsData([
                [$translator->translate('voyti-2fa.view.two_factor.disable', category: 'voyti-2fa'), 'type' => 'submit', 'class' => 'btn btn-danger', 'tabindex' => 2],
            ]);

        echo Html::form()->close();

        echo Html::hr();
        echo Html::H2($translator->translate('voyti-2fa.view.two_factor.regenerate_backup_codes', category: 'voyti-2fa'))->class('h5');
        echo Html::p($translator->translate('voyti-2fa.view.two_factor.regenerate_backup_codes_intro', category: 'voyti-2fa'))->class('text-muted small');

        if (!$data['hasBackupCodes']) {
            echo Html::div($translator->translate('voyti-2fa.view.two_factor.no_backup_codes_remaining', category: 'voyti-2fa'))->class('alert alert-warning');
        }

        echo Html::form()
            ->post($data['regenerateBackupCodesUrl'])
            ->csrf($csrf)
            ->open();

        echo Field::text($form, 'code')->inputId('voyti-2fa-regenerate-code')->tabIndex(3);

        echo Field::buttonGroup()
            ->buttonsData([
                [$translator->translate('voyti-2fa.view.two_factor.regenerate_backup_codes', category: 'voyti-2fa'), 'type' => 'submit', 'class' => LinkButtonHelper::submitButtonClass(), 'tabindex' => 4],
            ]);

        echo Html::form()->close();
    } else {
        // Client-collected method (e.g. WebAuthn): there is no code to type. Each action button runs
        // the method's assertion ceremony, which the fragment posts to the button's target URL. The
        // fragment is fetched fresh on click so its challenge is single-use and never collides across
        // the two actions.
        echo Html::button($translator->translate('voyti-2fa.view.two_factor.disable', category: 'voyti-2fa'))
            ->class('btn btn-danger')
            ->attribute('type', 'button')
            ->attribute('data-voyti-2fa-assertion', '')
            ->attribute('data-voyti-2fa-submit-url', $data['disableUrl'])
            ->attribute('tabindex', '1');

        echo Html::hr();
        echo Html::H2($translator->translate('voyti-2fa.view.two_factor.regenerate_backup_codes', category: 'voyti-2fa'))->class('h5');
        echo Html::p($translator->translate('voyti-2fa.view.two_factor.regenerate_backup_codes_intro', category: 'voyti-2fa'))->class('text-muted small');

        if (!$data['hasBackupCodes']) {
            echo Html::div($translator->translate('voyti-2fa.view.two_factor.no_backup_codes_remaining', category: 'voyti-2fa'))->class('alert alert-warning');
        }

        echo Html::button($translator->translate('voyti-2fa.view.two_factor.regenerate_backup_codes', category: 'voyti-2fa'))
            ->class(LinkButtonHelper::submitButtonClass())
            ->attribute('type', 'button')
            ->attribute('data-voyti-2fa-assertion', '')
            ->attribute('data-voyti-2fa-submit-url', $data['regenerateBackupCodesUrl'])
            ->attribute('tabindex', '2');

        // Shared host container the method fragment fills and posts from; empty until an action runs.
        echo Html::div()->id('voyti-2fa-assertion-host')->attribute('data-voyti-2fa-assertion-host', '')->open();
        echo Html::div()->close();

        $assertionConfig = [
            'fragmentUrl' => $data['reauthFragmentUrl'],
            'loadingLabel' => $translator->translate('voyti-2fa.view.two_factor.loading', category: 'voyti-2fa'),
        ];
        $assertionConfigJson = Json::htmlEncode($assertionConfig);

        $assertionJs = <<<JS
            (() => {
                const cfg = {$assertionConfigJson};

                const host = document.getElementById('voyti-2fa-assertion-host');
                const buttons = document.querySelectorAll('[data-voyti-2fa-assertion]');
                if (!host || !cfg.fragmentUrl) {
                    return;
                }

                const runAssertion = async submitUrl => {
                    host.setAttribute('data-voyti-2fa-submit-url', submitUrl);
                    host.innerHTML = '<div class="d-flex justify-content-center"><div class="spinner-border" role="status"><span class="visually-hidden">' + cfg.loadingLabel + '</span></div></div>';

                    try {
                        const response = await fetch(cfg.fragmentUrl, {
                            credentials: 'same-origin',
                            headers: {
                                Accept: 'text/html',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (!response.ok) {
                            throw new Error();
                        }

                        host.innerHTML = await response.text();

                        // Scripts inserted via innerHTML don't execute; re-inject them so the
                        // method's assertion ceremony runs.
                        host.querySelectorAll('script').forEach(script => {
                            const clone = document.createElement('script');
                            Array.from(script.attributes).forEach(attr => clone.setAttribute(attr.name, attr.value));
                            clone.textContent = script.textContent;
                            script.replaceWith(clone);
                        });
                    } catch {
                        location.href = cfg.fragmentUrl;
                    }
                };

                buttons.forEach(button => {
                    button.addEventListener('click', event => {
                        event.preventDefault();
                        runAssertion(button.getAttribute('data-voyti-2fa-submit-url'));
                    });
                });
            })();
            JS;

        echo Html::script($assertionJs)->render();
    }
} else {
    echo Html::div()->class('d-flex justify-content-center mb-3')->open();
    echo Html::div()->class('btn-group')->open();
    foreach ($data['methods'] as $methodButton) {
        echo Html::a($methodButton['label'], $data['methodUrls'][$methodButton['name']])
            ->class($data['method'] === $methodButton['name'] ? LinkButtonHelper::submitButtonClass() : LinkButtonHelper::resetButtonClass())
            ->attribute('data-voyti-2fa-method', $methodButton['name']);
    }
    echo Html::div()->close();
    echo Html::div()->close();

    echo Html::div()->id('voyti-2fa-content')->open();
    if ($data['preloadedFragmentHtml'] !== null) {
        echo Html::div($data['preloadedFragmentHtml'])->encode(false);
    } else {
        echo Html::div()->class('d-flex justify-content-center')->open();
        echo Html::div()
            ->class('spinner-border')
            ->attribute('role', 'status')
            ->content(Html::span($translator->translate('voyti-2fa.view.two_factor.loading', category: 'voyti-2fa'))->class('visually-hidden'));
        echo Html::div()->close();
    }
    echo Html::div()->close();

    $switchConfig = [
        // Json::encode() only reads public properties via get_object_vars(), so passing
        // the Csrf object itself would silently serialize as {} - force the string value.
        'csrfToken' => (string) $csrf,
        'autoloadUrl' => $data['autoloadUrl'],
        'autoloadMethod' => $data['method'],
        'activeClass' => LinkButtonHelper::submitButtonClass(),
        'inactiveClass' => LinkButtonHelper::resetButtonClass(),
    ];
    $switchConfigJson = Json::htmlEncode($switchConfig);

    $js = <<<JS
        (() => {
            const cfg = {$switchConfigJson};

            const content = document.getElementById('voyti-2fa-content');
            const buttons = document.querySelectorAll('[data-voyti-2fa-method]');

            const classNames = value => (value ? value.split(/\\s+/).filter(Boolean) : []);

            const setActive = method => {
                buttons.forEach(button => {
                    const active = button.getAttribute('data-voyti-2fa-method') === method;
                    const onClasses = classNames(active ? cfg.activeClass : cfg.inactiveClass);
                    const offClasses = classNames(active ? cfg.inactiveClass : cfg.activeClass);

                    offClasses.forEach(className => {
                        if (!onClasses.includes(className)) {
                            button.classList.remove(className);
                        }
                    });
                    onClasses.forEach(className => button.classList.add(className));
                });
            };

            const loadMethod = async (method, url) => {
                if (!content || !url) {
                    return;
                }

                try {
                    const response = await fetch(url, {
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'text/html',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        throw new Error();
                    }

                    const html = await response.text();
                    content.innerHTML = html;

                    // Scripts inserted via innerHTML don't execute, so re-inject them manually.
                    // Fragments register idempotent (guarded) event handlers so re-running them
                    // on every method switch stays safe.
                    content.querySelectorAll('script').forEach(script => {
                        const clone = document.createElement('script');
                        Array.from(script.attributes).forEach(attr => clone.setAttribute(attr.name, attr.value));
                        clone.textContent = script.textContent;
                        script.replaceWith(clone);
                    });

                    setActive(method);
                    history.replaceState(null, '', url);
                } catch {
                    location.href = url;
                }
            };

            buttons.forEach(button => {
                button.addEventListener('click', event => {
                    if (
                        event.defaultPrevented ||
                        event.button !== 0 ||
                        event.metaKey ||
                        event.ctrlKey ||
                        event.shiftKey ||
                        event.altKey
                    ) {
                        return;
                    }

                    event.preventDefault();
                    loadMethod(button.getAttribute('data-voyti-2fa-method'), button.href);
                });
            });

            if (cfg.autoloadUrl) {
                loadMethod(cfg.autoloadMethod, cfg.autoloadUrl);
            }
        })();
        JS;

    echo Html::script($js)->render();
}
echo Html::div()->close();
