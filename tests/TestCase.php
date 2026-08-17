<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\tests;

use Composer\InstalledVersions;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\Message\Php\MessageSource;
use Yiisoft\Translator\SimpleMessageFormatter;
use Yiisoft\Translator\Translator;
use Yiisoft\Translator\TranslatorInterface;

abstract class TestCase extends BaseTestCase
{
    protected function createTranslator(string $locale = 'en'): TranslatorInterface
    {
        $translator = new Translator($locale, null, 'voyti');
        $translator->addCategorySources(
            new CategorySource(
                'voyti',
                new MessageSource(InstalledVersions::getInstallPath('yiirocks/voyti') . '/resources/messages'),
                new SimpleMessageFormatter(),
            ),
            new CategorySource(
                'voyti-2fa',
                new MessageSource(dirname(__DIR__) . '/resources/messages'),
                new SimpleMessageFormatter(),
            ),
        );
        return $translator;
    }
}
