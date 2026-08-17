<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var array{
 *     menu: list<array{label: string, url: string, alignEnd: bool, routeName: string|null}>,
 *     codes: list<string>,
 *     continueUrl: string,
 * } $data
 * @var string $coreViews absolute base path of the core module's shared views
 * @var TranslatorInterface $translator
 * @var array{success: string|null, warning: string|null} $flash
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti-2fa.view.two_factor.backup_codes_title', category: 'voyti-2fa'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render($coreViews . '/shared/_menu', ['menu' => $data['menu']]);
/** @psalm-suppress InvalidScope */
echo $this->render($coreViews . '/shared/_flash');

echo Html::H1($translator->translate('voyti-2fa.view.two_factor.backup_codes_title', category: 'voyti-2fa'));

echo Html::div()->class('alert alert-warning')->open();
echo $translator->translate('voyti-2fa.view.two_factor.backup_codes_intro', category: 'voyti-2fa');
echo Html::div()->close();

echo Html::ul()->class('list-group mb-3')->open();
foreach ($data['codes'] as $code) {
    echo Html::li($code)->class('list-group-item font-monospace');
}
echo Html::ul()->close();

echo Html::a(
    $translator->translate('voyti-2fa.view.two_factor.backup_codes_continue', category: 'voyti-2fa'),
    $data['continueUrl'],
)->class('btn', 'btn-primary');

echo Html::div()->close();
