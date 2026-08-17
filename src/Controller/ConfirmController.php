<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\Controller;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\Auth\LoginCompletionService;
use YiiRocks\Voyti\TwoFactor\Auth\TwoFactorLoginChallenge;
use YiiRocks\Voyti\TwoFactor\Form\ConfirmForm;
use YiiRocks\Voyti\TwoFactor\Model\UserTwoFactor;
use YiiRocks\Voyti\TwoFactor\ResolvesPluginViewPath;
use YiiRocks\Voyti\TwoFactor\Service\BackupCodeService;
use YiiRocks\Voyti\TwoFactor\TwoFactorMethodInterface;
use YiiRocks\Voyti\TwoFactor\TwoFactorMethodRegistry;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\FormModel\FormHydrator;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Owns the two-factor login confirmation route (`voyti/session-confirm`): verifies the code (or a
 * backup code, or a client-collected payload) against the credentials stashed by
 * {@see TwoFactorLoginChallenge}, then finalizes login through the
 * core {@see LoginCompletionService}.
 */
final readonly class ConfirmController
{
    use RenderTrait;
    use ResolvesPluginViewPath {
        ResolvesPluginViewPath::resolveViewPath insteadof RenderTrait;
    }

    private const string SESSION_KEY_CREDENTIALS = 'credentials';

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private UrlGeneratorInterface $url,
        private VoytiConfig $config,
        private ResponseFactoryInterface $responseFactory,
        private SessionInterface $session,
        private FormHydrator $formHydrator,
        private TwoFactorMethodRegistry $twoFactorMethods,
        private BackupCodeService $backupCodeService,
        private LoginCompletionService $loginCompletionService,
    ) {}

    public function confirm(ServerRequestInterface $request): ResponseInterface
    {
        /** @var mixed $credentialsValue */
        $credentialsValue = $this->session->get(self::SESSION_KEY_CREDENTIALS);
        $credentials = is_array($credentialsValue) ? $credentialsValue : [];
        if ($credentials === []) {
            return $this->redirect($this->url->generate('voyti/session-login'));
        }

        /** @var mixed $loginValue */
        $loginValue = $credentials['login'] ?? '';
        $login = is_string($loginValue) ? $loginValue : '';
        $user = User::findByUsernameOrEmail($login);
        $method = $user !== null
            ? $this->resolveMethod(UserTwoFactor::forUser($user)->getMethod())
            : $this->twoFactorMethods->getDefault();

        $form = new ConfirmForm($this->translator);

        if ($user !== null) {
            if ($method->isCodeBased()) {
                if ($this->formHydrator->populateFromPostAndValidate($form, $request)) {
                    $code = $form->twoFactorAuthenticationCode;

                    if ($method->verify($user, ['code' => $code]) || $this->backupCodeService->consume($user, $code)) {
                        return $this->completeConfirmation($user, $credentials, $request);
                    }

                    $errorMessage = $method->getErrorMessage();
                    $form->addError(
                        $errorMessage !== ''
                            ? $errorMessage
                            : $this->translator->translate('voyti-2fa.validator.invalid_verification_code', category: 'voyti-2fa'),
                        ['twoFactorAuthenticationCode'],
                    );
                }
            } elseif ($method->verify($user, ['payload' => (string) $request->getBody(), 'domain' => $request->getUri()->getHost()])) {
                return $this->completeConfirmation($user, $credentials, $request);
            }
        }

        return $this->renderView('two-factor/confirm', [
            'form' => $form,
            'data' => [
                'isCodeBased' => $method->isCodeBased(),
                'methodFragmentUrl' => $method->getConfirmFragmentUrl($this->url),
                'formSubmitUrl' => $this->url->generate('voyti/session-confirm'),
            ],
        ]);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function boolValue(array $data, string $key): bool
    {
        /** @var mixed $value */
        $value = $data[$key] ?? false;
        $boolValue = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        /** @infection-ignore-all Defensive fallback for values filter_var can't parse; the challenge only ever stores a real bool. */
        return $boolValue ?? (bool) $value;
    }

    /**
     * @param array<array-key, mixed> $credentials
     */
    private function completeConfirmation(User $user, array $credentials, ServerRequestInterface $request): ResponseInterface
    {
        $this->session->remove(self::SESSION_KEY_CREDENTIALS);

        return $this->loginCompletionService->complete($user, $this->boolValue($credentials, 'rememberMe'), $request);
    }

    private function redirect(string $url): ResponseInterface
    {
        return $this->responseFactory->createResponse(Status::FOUND)->withHeader(Header::LOCATION, $url);
    }

    /**
     * Falls back to the default method when the stored type is null or no longer registered.
     */
    private function resolveMethod(?string $name): TwoFactorMethodInterface
    {
        return $this->twoFactorMethods->has($name)
            ? $this->twoFactorMethods->get((string) $name)
            : $this->twoFactorMethods->getDefault();
    }
}
