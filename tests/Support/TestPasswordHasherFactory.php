<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\tests\Support;

use Yiisoft\Security\PasswordHasher;

/**
 * Builds a `PasswordHasher` at the lowest valid bcrypt cost, so tests exercising real
 * hash/verify calls (backup codes) don't pay the production cost of 13.
 */
final class TestPasswordHasherFactory
{
    public static function create(): PasswordHasher
    {
        return new PasswordHasher(PASSWORD_BCRYPT, ['cost' => 4]);
    }
}
