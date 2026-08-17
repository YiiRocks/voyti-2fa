<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\tests\Support;

use Composer\InstalledVersions;
use ReflectionClass;
use YiiRocks\Voyti\VoytiConfig;

/**
 * Builds a {@see VoytiConfig} from the core package's real `config/params.php` defaults, with
 * per-test overrides layered on top.
 */
final class VoytiConfigFactory
{
    public static function create(mixed ...$overrides): VoytiConfig
    {
        return new VoytiConfig(...[...self::defaults(), ...$overrides]);
    }

    /**
     * @psalm-suppress MixedArgument, UnresolvableInclude
     */
    private static function defaults(): array
    {
        $params = require InstalledVersions::getInstallPath('yiirocks/voyti') . '/config/params.php';

        // Keep only keys that map to a VoytiConfig constructor parameter.
        $constructorParameters = array_column(
            (new ReflectionClass(VoytiConfig::class))->getConstructor()?->getParameters() ?? [],
            'name',
        );

        $voytiParams = $params['yiirocks/voyti'];
        $defaults = array_intersect_key($voytiParams, array_flip($constructorParameters));

        if (in_array('twoFactorEnabled', $constructorParameters, true)) {
            $defaults['twoFactorEnabled'] = ($voytiParams['twoFactorMethodRoutes'] ?? []) !== [];
        }

        return $defaults;
    }
}
