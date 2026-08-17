<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\tests\Helper\Views;

use YiiRocks\Voyti\TwoFactor\Helper\Views\IndexView;
use YiiRocks\Voyti\TwoFactor\tests\Support\FakeTwoFactorMethod;
use YiiRocks\Voyti\TwoFactor\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\TwoFactor\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\TwoFactor\tests\TestCase;

final class IndexViewTest extends TestCase
{
    public function testExposesReauthFragmentForNonCodeBasedMethod(): void
    {
        $method = new FakeTwoFactorMethod(name: 'webauthn', codeBased: false, reauthFragmentUrl: '//voyti/webauthn-reauth');
        $data = IndexView::create(
            isEnabled: true,
            method: $method,
            errors: [],
            codeDelivered: false,
            hasBackupCodes: true,
            preloadedFragmentHtml: null,
            availableMethods: [$method],
            config: VoytiConfigFactory::create(),
            url: new FakeUrlGenerator(),
            translator: $this->createTranslator(),
        );

        // Client-collected method: the settings page lazy-loads the assertion fragment for the
        // disable/regenerate actions rather than rendering a code field.
        self::assertFalse($data['isCodeBased']);
        self::assertSame('//voyti/webauthn-reauth', $data['reauthFragmentUrl']);
    }

    public function testLazyLoadsFragmentWhenNonePreloaded(): void
    {
        $method = new FakeTwoFactorMethod(name: 'totp', settingsUrl: '//voyti/totp-settings');
        $data = IndexView::create(
            isEnabled: false,
            method: $method,
            errors: ['code' => ['Bad.']],
            codeDelivered: false,
            hasBackupCodes: false,
            preloadedFragmentHtml: null,
            availableMethods: [$method, new FakeTwoFactorMethod(name: 'email', buttonLabel: 'Email', settingsUrl: '//voyti/email-settings')],
            config: VoytiConfigFactory::create(),
            url: new FakeUrlGenerator(),
            translator: $this->createTranslator(),
        );

        self::assertFalse($data['isEnabled']);
        self::assertSame('totp', $data['method']);
        self::assertSame(['code' => ['Bad.']], $data['errors']);
        // Code-based method: inline code entry, so no re-auth fragment to lazy-load.
        self::assertTrue($data['isCodeBased']);
        self::assertNull($data['reauthFragmentUrl']);
        self::assertSame(
            [
                ['name' => 'totp', 'label' => 'Fake'],
                ['name' => 'email', 'label' => 'Email'],
            ],
            $data['methods'],
        );
        self::assertSame(
            ['totp' => '//voyti/totp-settings', 'email' => '//voyti/email-settings'],
            $data['methodUrls'],
        );
        self::assertSame('//voyti/user-two-factor-disable', $data['disableUrl']);
        self::assertSame('//voyti/user-two-factor-disable-send-code', $data['disableSendCodeUrl']);
        self::assertSame('//voyti/user-two-factor-regenerate-backup-codes', $data['regenerateBackupCodesUrl']);
        self::assertFalse($data['hasBackupCodes']);
        // No preloaded fragment -> the page must lazy-load it from the method's settings URL.
        self::assertNull($data['preloadedFragmentHtml']);
        self::assertSame('//voyti/totp-settings', $data['autoloadUrl']);

        // Regression guard: the account menu is built with the caller's `voyti`-default translator, so
        // core menu labels resolve to real text rather than passing through as raw keys.
        $labels = array_column($data['menu'], 'label');
        self::assertContains('Dashboard', $labels);
        self::assertContains('Log out', $labels);
        self::assertNotContains('voyti.menu.dashboard', $labels);
    }

    public function testUsesPreloadedFragment(): void
    {
        $method = new FakeTwoFactorMethod(name: 'totp', codeDelivery: true, enabledWithMethodName: 'TOTP');
        $data = IndexView::create(
            isEnabled: true,
            method: $method,
            errors: [],
            codeDelivered: true,
            hasBackupCodes: true,
            preloadedFragmentHtml: '<div>setup</div>',
            availableMethods: [$method],
            config: VoytiConfigFactory::create(),
            url: new FakeUrlGenerator(),
            translator: $this->createTranslator(),
        );

        self::assertTrue($data['isEnabled']);
        self::assertTrue($data['requiresCodeDelivery']);
        self::assertTrue($data['codeDelivered']);
        self::assertTrue($data['hasBackupCodes']);
        self::assertSame('Two-factor authentication via TOTP is enabled', $data['enabledWithMethodMessage']);
        self::assertSame('<div>setup</div>', $data['preloadedFragmentHtml']);
        // A preloaded fragment means no lazy-load round-trip.
        self::assertNull($data['autoloadUrl']);
    }
}
