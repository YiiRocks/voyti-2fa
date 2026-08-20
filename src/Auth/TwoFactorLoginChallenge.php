<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\Auth;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\Auth\LoginChallengeInterface;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\TwoFactor\Controller\ConfirmController;
use YiiRocks\Voyti\TwoFactor\Form\ConfirmForm;
use YiiRocks\Voyti\TwoFactor\Model\UserTwoFactor;
use YiiRocks\Voyti\TwoFactor\TwoFactorMethodRegistry;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Login challenge that inserts the two-factor confirmation step: when the user has an enabled,
 * registered method, it starts that method's step, stashes the pending credentials, and renders the
 * confirmation screen. {@see ConfirmController::confirm()} then
 * completes login once the code is verified.
 */
final readonly class TwoFactorLoginChallenge implements LoginChallengeInterface
{
    use RenderTrait;

    private const string SESSION_KEY_CREDENTIALS = 'credentials';

    public function __construct(
        private TwoFactorMethodRegistry $twoFactorMethods,
        private SessionInterface $session,
        private WebViewRenderer $viewRenderer,
        private VoytiConfig $config,
        private UrlGeneratorInterface $url,
        private TranslatorInterface $translator,
    ) {}

    #[Override]
    public function challenge(User $user, bool $rememberMe, ServerRequestInterface $request): ?ResponseInterface
    {
        $twoFactor = UserTwoFactor::forUser($user);
        if (!$twoFactor->isEnabled() || !$this->twoFactorMethods->has($twoFactor->getMethod())) {
            return null;
        }

        $method = $this->twoFactorMethods->get((string) $twoFactor->getMethod());
        $method->onAuthenticationStepStart($user);

        $this->session->set(self::SESSION_KEY_CREDENTIALS, [
            'login' => $user->getUsername(),
            'rememberMe' => $rememberMe,
        ]);

        return $this->renderView('two-factor/confirm', [
            'form' => new ConfirmForm($this->translator),
            'data' => [
                'isCodeBased' => $method->isCodeBased(),
                'methodFragmentUrl' => $method->getConfirmFragmentUrl($this->url),
                'formSubmitUrl' => $this->url->generate('voyti/session-confirm'),
            ],
        ]);
    }
}
