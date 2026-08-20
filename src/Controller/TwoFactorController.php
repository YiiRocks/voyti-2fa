<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\Controller;

use Composer\InstalledVersions;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Controller\RedirectTrait;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Helper\FlashType;
use YiiRocks\Voyti\Helper\Views\MenuView;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\FlashNotifier;
use YiiRocks\Voyti\TwoFactor\Form\TwoFactorCodeForm;
use YiiRocks\Voyti\TwoFactor\Helper\Views\IndexView;
use YiiRocks\Voyti\TwoFactor\Model\UserTwoFactor;
use YiiRocks\Voyti\TwoFactor\ResolvesPluginViewPath;
use YiiRocks\Voyti\TwoFactor\Service\BackupCodeService;
use YiiRocks\Voyti\TwoFactor\TwoFactorMethodInterface;
use YiiRocks\Voyti\TwoFactor\TwoFactorMethodRegistry;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Input\Http\Attribute\Parameter\Body;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Manages two-factor authentication for the current user, generically across every registered
 * method: enabling/disabling, the disable "deliver a code first" pre-step for methods that need it,
 * and generating/regenerating backup codes. Each method (e.g. email via `yiirocks/voyti-2fa-email`,
 * TOTP via `yiirocks/voyti-2fa-totp`) contributes its own setup route, handled in its own package.
 * The user's 2FA state lives in {@see UserTwoFactor}, kept out of the core user table.
 */
final readonly class TwoFactorController
{
    use RedirectTrait;
    use RenderTrait;
    use ResolvesPluginViewPath {
        ResolvesPluginViewPath::resolveViewPath insteadof RenderTrait;
    }

    private const string SESSION_KEY_BACKUP_CODES = 'backupCodes';

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private UrlGeneratorInterface $url,
        private VoytiConfig $config,
        private CurrentUser $currentUser,
        private ResponseFactoryInterface $responseFactory,
        private SessionInterface $session,
        private FlashNotifier $flashNotifier,
        private BackupCodeService $backupCodeService,
        private TwoFactorMethodRegistry $twoFactorMethods,
    ) {}

    public function disable(ServerRequestInterface $request, #[Body('code')] string $code = ''): ResponseInterface
    {
        /** @var User $user */
        $user = $this->currentUser->getIdentity();
        $twoFactor = UserTwoFactor::forUser($user);

        $method = $this->resolveMethod($twoFactor->getMethod());

        $isValid = $this->verifyReauthentication($user, $method, $code, $request);

        if (!$isValid) {
            return $this->renderTwoFactorIndex(
                $user,
                $method,
                errors: ['code' => [$this->errorMessage($method->getErrorMessage())]],
                codeDelivered: $method->requiresCodeDelivery(),
            );
        }

        $method->onDisable($user);
        $twoFactor->setEnabled(false);
        $twoFactor->setSecret(null);
        $twoFactor->setMethod(null);
        $twoFactor->save();
        $this->backupCodeService->clear($user);

        return $this->flashRedirect('voyti-2fa.settings.two_factor_disabled');
    }

    public function disableSendCode(): ResponseInterface
    {
        /** @var User $user */
        $user = $this->currentUser->getIdentity();
        $twoFactor = UserTwoFactor::forUser($user);

        if (!$twoFactor->isEnabled()) {
            return $this->redirect($this->url->generate('voyti/user-two-factor'));
        }

        $method = $this->resolveMethod($twoFactor->getMethod());

        if (!$method->requiresCodeDelivery()) {
            return $this->redirect($this->url->generate('voyti/user-two-factor'));
        }

        $method->onAuthenticationStepStart($user);

        return $this->renderTwoFactorIndex($user, $method, codeDelivered: true);
    }

    public function enable(
        #[Body('method')]
        string $method = '',
        #[Body('code')]
        string $code = '',
    ): ResponseInterface {
        /** @var User $user */
        $user = $this->currentUser->getIdentity();
        $twoFactor = UserTwoFactor::forUser($user);

        if ($twoFactor->isEnabled()) {
            return $this->flashRedirect('voyti-2fa.settings.two_factor_enabled');
        }

        if (!$this->twoFactorMethods->has($method)) {
            if (!$this->twoFactorMethods->hasAvailable()) {
                return $this->redirect($this->url->generate('voyti/user-two-factor'));
            }

            $method = $this->twoFactorMethods->getDefault()->getName();
        }

        /** @var TwoFactorMethodInterface $twoFactorMethod */
        $twoFactorMethod = $this->twoFactorMethods->get($method);

        if (!$twoFactorMethod->isCodeBased()) {
            return $this->redirect($this->url->generate('voyti/user-two-factor'));
        }

        if (!$twoFactorMethod->verify($user, ['code' => $code])) {
            // Each method renders its own setup fragment (QR code, email form, ...); on a failed
            // code the page lazy-loads it via the method's settings URL rather than inlining it here.
            return $this->renderTwoFactorIndex(
                $user,
                $twoFactorMethod,
                errors: ['code' => [$this->errorMessage($twoFactorMethod->getErrorMessage())]],
            );
        }

        $twoFactor->setMethod($twoFactorMethod->getName());
        $twoFactor->setEnabled(true);
        $twoFactor->save();

        return $this->renderBackupCodes($this->backupCodeService->generate($user));
    }

    public function index(): ResponseInterface
    {
        // Arriving here means the user has moved on from the backup-codes reveal (if any was
        // pending), so the one-time stash from regenerateBackupCodes() is done being reachable.
        $this->session->remove(self::SESSION_KEY_BACKUP_CODES);

        /** @var User $user */
        $user = $this->currentUser->getIdentity();
        $twoFactor = UserTwoFactor::forUser($user);

        /** @infection-ignore-all Ternary: 2FA enablement check routes to different flows. */
        if ($twoFactor->isEnabled()) {
            return $this->renderTwoFactorIndex($user, $this->resolveMethod($twoFactor->getMethod()));
        }

        if (!$this->twoFactorMethods->hasAvailable()) {
            return $this->renderMethodsUnavailable();
        }

        return $this->renderTwoFactorIndex($user, $this->twoFactorMethods->getDefault());
    }

    public function regenerateBackupCodes(ServerRequestInterface $request, #[Body('code')] string $code = ''): ResponseInterface
    {
        /** @var User $user */
        $user = $this->currentUser->getIdentity();
        $twoFactor = UserTwoFactor::forUser($user);

        if (!$twoFactor->isEnabled()) {
            return $this->redirect($this->url->generate('voyti/user-two-factor'));
        }

        $method = $this->resolveMethod($twoFactor->getMethod());

        $isValid = $this->verifyReauthentication($user, $method, $code, $request);

        if (!$isValid) {
            return $this->renderTwoFactorIndex(
                $user,
                $method,
                errors: ['code' => [$this->errorMessage($method->getErrorMessage())]],
            );
        }

        $this->session->set(self::SESSION_KEY_BACKUP_CODES, $this->backupCodeService->generate($user));

        return $this->redirect($this->url->generate('voyti/user-two-factor-backup-codes'));
    }

    /**
     * Displays codes stashed by a prior {@see regenerateBackupCodes()} redirect. Reading this route
     * doesn't clear the stash - {@see index()} does, once the user moves on - because the WebAuthn
     * reauth ceremony's fetch() silently follows the redirect once before the browser navigates here
     * a second time for real; clearing on the first (discarded) read would hide the codes from the
     * second (visible) one.
     */
    public function showBackupCodes(): ResponseInterface
    {
        /** @var mixed $codesValue */
        $codesValue = $this->session->get(self::SESSION_KEY_BACKUP_CODES);

        /** @var list<string> $codes */
        $codes = [];
        if (is_array($codesValue)) {
            /** @var mixed $code */
            foreach ($codesValue as $code) {
                if (is_string($code)) {
                    $codes[] = $code;
                }
            }
        }

        if ($codes === []) {
            return $this->redirect($this->url->generate('voyti/user-two-factor'));
        }

        return $this->renderBackupCodes($codes);
    }

    /**
     * Absolute base path of the core module's bundled views, so this package's own templates can
     * render the shared account chrome (menu, flash) that stays in core.
     */
    private function coreViewPath(): string
    {
        /** @var non-empty-string $corePath */
        $corePath = InstalledVersions::getInstallPath('yiirocks/voyti');

        return $corePath . '/resources/views/' . $this->config->webTheme->value;
    }

    private function errorMessage(string $validatorMessage): string
    {
        return $validatorMessage !== ''
            ? $validatorMessage
            : $this->translator->translate('voyti-2fa.validator.invalid_verification_code', category: 'voyti-2fa');
    }

    private function flashRedirect(string $messageKey): ResponseInterface
    {
        $this->flashNotifier->add(FlashType::SUCCESS, $this->translator->translate($messageKey, category: 'voyti-2fa'));

        return $this->redirect($this->url->generate('voyti/user-two-factor'));
    }

    /**
     * @param list<string> $codes
     */
    private function renderBackupCodes(array $codes): ResponseInterface
    {
        return $this->renderView('two-factor/backup-codes', [
            'coreViews' => $this->coreViewPath(),
            'data' => [
                'menu' => MenuView::account($this->config, $this->url, $this->translator()),
                'codes' => $codes,
                'continueUrl' => $this->url->generate('voyti/user-two-factor'),
            ],
        ]);
    }

    private function renderMethodsUnavailable(): ResponseInterface
    {
        return $this->renderView('two-factor/unavailable', [
            'coreViews' => $this->coreViewPath(),
            'data' => [
                'menu' => MenuView::account($this->config, $this->url, $this->translator()),
            ],
        ]);
    }

    /**
     * @param array<string, list<string>> $errors
     */
    private function renderTwoFactorIndex(
        User $user,
        TwoFactorMethodInterface $method,
        array $errors = [],
        bool $codeDelivered = false,
        ?string $preloadedFragmentHtml = null,
    ): ResponseInterface {
        return $this->renderView('two-factor/index', [
            'coreViews' => $this->coreViewPath(),
            'form' => new TwoFactorCodeForm($this->translator, $method->getName()),
            'data' => IndexView::create(
                UserTwoFactor::forUser($user)->isEnabled(),
                $method,
                $errors,
                $codeDelivered,
                $this->backupCodeService->hasUnused($user),
                $preloadedFragmentHtml,
                $this->twoFactorMethods->getAvailable(),
                $this->config,
                $this->url,
                $this->translator(),
            ),
        ]);
    }

    /**
     * Falls back to the default method when the stored type is null or no longer registered
     * (e.g. a host removed a method package while a user still has that type enabled).
     */
    private function resolveMethod(?string $name): TwoFactorMethodInterface
    {
        return $this->twoFactorMethods->has($name)
            ? $this->twoFactorMethods->get((string) $name)
            : $this->twoFactorMethods->getDefault();
    }

    /**
     * Re-verifies the user before a sensitive settings action (disabling 2FA, regenerating backup
     * codes). Code-based methods (TOTP/email) accept a typed code or one of the user's backup codes;
     * client-collected methods (WebAuthn) verify the assertion posted in the raw request body -
     * mirroring {@see ConfirmController} so a method without a code isn't forced onto the backup-code
     * path just to satisfy the form.
     */
    private function verifyReauthentication(
        User $user,
        TwoFactorMethodInterface $method,
        string $code,
        ServerRequestInterface $request,
    ): bool {
        if ($method->isCodeBased()) {
            return $method->verify($user, ['code' => $code]) || $this->backupCodeService->consume($user, $code);
        }

        return $method->verify($user, [
            'payload' => (string) $request->getBody(),
            // Client-collected methods (WebAuthn) bind verification to the request domain (its
            // relying-party id); pass it so the assertion validates against the issuing domain.
            'domain' => $request->getUri()->getHost(),
        ]);
    }
}
