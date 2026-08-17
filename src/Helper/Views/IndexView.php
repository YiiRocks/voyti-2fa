<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\Helper\Views;

use YiiRocks\Voyti\Helper\Views\MenuView;
use YiiRocks\Voyti\TwoFactor\TwoFactorMethodInterface;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Builds the data array for the `two-factor/index` screen. The method-switch buttons are built from
 * every available method, and the selected method's setup fragment is either preloaded inline
 * (`preloadedFragmentHtml`) or lazy-loaded by the page's own JavaScript via `autoloadUrl`.
 *
 * Shared by the base `TwoFactorController` and every method package's setup controller (email, TOTP,
 * WebAuthn), which preload their own setup fragment on this screen. Callers pass a translator whose
 * default category is `voyti` (via the controllers' `translator()`), so the menu labels resolve.
 */
final class IndexView
{
    /**
     * @param bool $isEnabled whether 2FA is currently turned on for this user
     * @param TwoFactorMethodInterface $method the selected/active method, independent of $isEnabled
     * @param array<string, list<string>> $errors
     * @param bool $codeDelivered whether a disable-confirmation code was already delivered in this
     *        flow; when true, `disableUrl` expects that code instead of `disableSendCodeUrl` being usable
     * @param bool $hasBackupCodes whether the user currently has unused backup codes
     * @param string|null $preloadedFragmentHtml the active method's setup fragment rendered inline
     *        (no extra AJAX round-trip); null when the page must lazy-load it via `autoloadUrl`
     * @param list<TwoFactorMethodInterface> $availableMethods methods that pass
     *        {@see TwoFactorMethodInterface::isAvailable()}, used for the method-switch buttons
     *
     * @return array{
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
     * }
     */
    public static function create(
        bool $isEnabled,
        TwoFactorMethodInterface $method,
        array $errors,
        bool $codeDelivered,
        bool $hasBackupCodes,
        ?string $preloadedFragmentHtml,
        array $availableMethods,
        VoytiConfig $config,
        UrlGeneratorInterface $url,
        TranslatorInterface $translator,
    ): array {
        $methods = [];
        $methodUrls = [];
        foreach ($availableMethods as $availableMethod) {
            $methods[] = [
                'name' => $availableMethod->getName(),
                'label' => $availableMethod->getButtonLabel($translator),
            ];
            $methodUrls[$availableMethod->getName()] = $availableMethod->getSettingsUrl($url);
        }

        return [
            'menu' => MenuView::account($config, $url, $translator),
            'errors' => $errors,
            'isEnabled' => $isEnabled,
            'method' => $method->getName(),
            'enabledWithMethodMessage' => $translator->translate(
                'voyti-2fa.view.two_factor.enabled_with_method',
                ['method' => $method->getEnabledWithMethodName($translator)],
                category: 'voyti-2fa',
            ),
            'codeDelivered' => $codeDelivered,
            'requiresCodeDelivery' => $method->requiresCodeDelivery(),
            'isCodeBased' => $method->isCodeBased(),
            'reauthFragmentUrl' => $method->getReauthFragmentUrl($url),
            'disableSendCodeUrl' => $url->generate('voyti/user-two-factor-disable-send-code'),
            'disableUrl' => $url->generate('voyti/user-two-factor-disable'),
            'hasBackupCodes' => $hasBackupCodes,
            'regenerateBackupCodesUrl' => $url->generate('voyti/user-two-factor-regenerate-backup-codes'),
            'methods' => $methods,
            'methodUrls' => $methodUrls,
            'preloadedFragmentHtml' => $preloadedFragmentHtml,
            'autoloadUrl' => $preloadedFragmentHtml !== null ? null : $method->getSettingsUrl($url),
        ];
    }
}
