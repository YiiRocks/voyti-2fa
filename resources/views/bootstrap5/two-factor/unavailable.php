<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var array{
 *     menu: list<array{label: string, url: string, alignEnd: bool, routeName: string|null}>,
 * } $data
 * @var string $coreViews absolute base path of the core module's shared views
 * @var TranslatorInterface $translator
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti-2fa.view.two_factor.title', category: 'voyti-2fa'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render($coreViews . '/shared/_menu', ['menu' => $data['menu']]);
/** @psalm-suppress InvalidScope */
echo $this->render($coreViews . '/shared/_flash');

echo Html::H1($translator->translate('voyti-2fa.view.two_factor.title', category: 'voyti-2fa'));

echo Html::div($translator->translate('voyti-2fa.view.two_factor.unavailable', category: 'voyti-2fa'))
    ->class('alert alert-info');
echo Html::div()->close();
