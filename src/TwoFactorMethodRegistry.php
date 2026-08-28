<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor;

use InvalidArgumentException;
use LogicException;

/**
 * Holds every registered two-factor method keyed by {@see TwoFactorMethodInterface::getName()}.
 * Built from all services tagged `voyti.two-factor-method` in the DI container, so method packages
 * add methods by tagging their own providers rather than overriding this class.
 *
 * The given iterable is resolved lazily, on first lookup, not in the constructor.
 */
final class TwoFactorMethodRegistry
{
    /** @var array<string, TwoFactorMethodInterface>|null */
    private ?array $resolvedMethods = null;

    /**
     * @param iterable<TwoFactorMethodInterface> $methods
     */
    public function __construct(private readonly iterable $methods = []) {}

    /**
     * @throws InvalidArgumentException if no method with the given name is registered
     */
    public function get(string $name): TwoFactorMethodInterface
    {
        $methods = $this->resolveMethods();
        if (!isset($methods[$name])) {
            throw new InvalidArgumentException(sprintf('Unknown two-factor method "%s".', $name));
        }

        return $methods[$name];
    }

    /**
     * @return list<TwoFactorMethodInterface> available methods in registration order
     */
    public function getAvailable(): array
    {
        return array_values(array_filter(
            $this->resolveMethods(),
            static fn(TwoFactorMethodInterface $method): bool => $method->isAvailable(),
        ));
    }

    /**
     * The first available method, used as the preselected one on the settings screen and as the
     * fallback when a stored type is no longer registered.
     *
     * @throws LogicException if no method is registered at all
     */
    public function getDefault(): TwoFactorMethodInterface
    {
        $methods = $this->getAvailable();
        if ($methods === []) {
            throw new LogicException('No two-factor authentication methods are available.');
        }

        return $methods[0];
    }

    public function has(?string $name): bool
    {
        return $name !== null && isset($this->resolveMethods()[$name]);
    }

    /**
     * Whether at least one registered method is available, i.e. its backing library is installed.
     * Callers use this to guard {@see self::getDefault()} instead of letting it throw when a host
     * installs this base package without any method package.
     */
    public function hasAvailable(): bool
    {
        return $this->getAvailable() !== [];
    }

    /**
     * @return array<string, TwoFactorMethodInterface>
     */
    private function resolveMethods(): array
    {
        if ($this->resolvedMethods === null) {
            $this->resolvedMethods = [];
            foreach ($this->methods as $method) {
                $this->resolvedMethods[$method->getName()] = $method;
            }
        }

        return $this->resolvedMethods;
    }
}
