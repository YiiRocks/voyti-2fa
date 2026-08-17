<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor;

use Composer\InstalledVersions;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\VoytiConfig;

/**
 * Resolves a view path for this package's controllers and login challenge, overriding
 * {@see RenderTrait::resolveViewPath()}: a host's configured `viewPath`
 * override wins, then this package's own bundled views, then the core module's shared views. The
 * using class must expose a readonly {@see VoytiConfig} `$config`.
 */
trait ResolvesPluginViewPath
{
    /**
     * @psalm-suppress UndefinedThisPropertyFetch
     */
    private function resolveViewPath(string $view): string
    {
        if ($this->config->viewPath !== null && is_file($this->config->viewPath . '/' . $view . '.php')) {
            return $this->config->viewPath;
        }

        $pluginPath = dirname(__DIR__) . '/resources/views/' . $this->config->webTheme->value;
        if (is_file($pluginPath . '/' . $view . '.php')) {
            return $pluginPath;
        }

        // @codeCoverageIgnoreStart
        // Defensive fallback: every view rendered through this trait ships in this package, so the
        // core lookup is unreachable in practice; kept for a host that references a core-only view.
        /** @var non-empty-string $corePath */
        $corePath = InstalledVersions::getInstallPath('yiirocks/voyti');

        return $corePath . '/resources/views/' . $this->config->webTheme->value;
        // @codeCoverageIgnoreEnd
    }
}
