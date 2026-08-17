<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor;

use YiiRocks\Voyti\Model\User;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * A pluggable two-factor authentication method registered in {@see TwoFactorMethodRegistry}. Methods
 * ship as their own packages (e.g. `yiirocks/voyti-2fa-email`, `yiirocks/voyti-2fa-totp`) and are
 * collected via the `voyti.two-factor-method` DI tag.
 */
interface TwoFactorMethodInterface
{
    /**
     * Short label for the method-switch button on the settings screen.
     */
    public function getButtonLabel(TranslatorInterface $translator): string;

    /**
     * GET route serving this method's login-confirmation fragment, or null for code-based methods
     * whose confirm form is rendered inline by the `session/confirm` template.
     */
    public function getConfirmFragmentUrl(UrlGeneratorInterface $url): ?string;

    /**
     * Translated method name used in the "2FA is enabled with {method}" message.
     */
    public function getEnabledWithMethodName(TranslatorInterface $translator): string;

    /**
     * Translated error message from the most recent failed {@see verify()} call; empty string when
     * the last verification succeeded or was never attempted.
     */
    public function getErrorMessage(): string;

    /**
     * The exclusive method name this method is stored under (e.g. 'totp'); also the registry key.
     */
    public function getName(): string;

    /**
     * GET route serving this method's re-authentication fragment for the settings screen's sensitive
     * actions (disabling 2FA, regenerating backup codes); null for code-based methods, whose
     * re-authentication is a typed code rendered inline. For client-collected methods (WebAuthn) the
     * fragment runs the assertion ceremony and posts its result to the submit URL the settings page
     * supplies on the host container, so a single fragment serves both actions.
     */
    public function getReauthFragmentUrl(UrlGeneratorInterface $url): ?string;

    /**
     * GET route for this method's settings screen (also fetched by the settings page's JavaScript
     * to lazy-load the setup fragment).
     */
    public function getSettingsUrl(UrlGeneratorInterface $url): string;

    /**
     * Whether the method's backing library is installed; unavailable methods are hidden from the
     * settings screen and never chosen as the default.
     */
    public function isAvailable(): bool;

    /**
     * Whether verification happens against a user-typed code on the confirm screen (TOTP/email) as
     * opposed to a client-collected payload posted by the browser (WebAuthn).
     */
    public function isCodeBased(): bool;

    /**
     * Runs when a login hits this method's confirmation step (e.g. emails a fresh code).
     */
    public function onAuthenticationStepStart(User $user): void;

    /**
     * Runs when two-factor authentication is disabled entirely, allowing the method to clear its
     * user-specific state (e.g. registered hardware keys).
     */
    public function onDisable(User $user): void;

    /**
     * Whether the code must be delivered to the user before they can enter it (e.g. email sends a
     * one-time code), as opposed to being available on demand (TOTP authenticator app, WebAuthn).
     * Drives the disable flow's "send a code first" pre-step generically, without special-casing any
     * method by name.
     */
    public function requiresCodeDelivery(): bool;

    /**
     * @param array<string, string> $data verification input: `['code' => $code]` for code-based
     *        methods; `['payload' => $rawBody, 'domain' => $requestHost]` for client-collected methods,
     *        where `domain` is the request host the assertion challenge was issued for (its
     *        relying-party id) and must match for verification to succeed
     */
    public function verify(User $user, array $data): bool;
}
