<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\tests\Support;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\TwoFactor\Model\UserTwoFactor;

/**
 * Builds real users and their two-factor records for tests. 2FA state lives in {@see UserTwoFactor},
 * not on the user row, so a user is created plain and its 2FA record separately.
 */
trait UserFactoryTrait
{
    private function createUser(
        string $username = 'testuser',
        string $email = 'test@example.com',
        string $passwordHash = 'hash',
        ?int $createdAt = null,
        ?int $confirmedAt = null,
        ?int $blockedAt = null,
    ): User {
        $timestamp = $createdAt ?? time();

        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash($passwordHash);
        $user->setAuthKey('key');
        $user->setCreatedAt($timestamp);
        $user->setUpdatedAt($timestamp);
        if ($confirmedAt !== null) {
            $user->setConfirmedAt($confirmedAt);
        }
        if ($blockedAt !== null) {
            $user->setBlockedAt($blockedAt);
        }
        $user->save();

        return $user;
    }

    private function createUserTwoFactor(
        int $userId,
        bool $enabled = true,
        ?string $method = 'email',
        ?string $secret = null,
    ): UserTwoFactor {
        $record = new UserTwoFactor();
        $record->setUserId($userId);
        $record->setEnabled($enabled);
        $record->setMethod($method);
        $record->setSecret($secret);
        $record->save();

        return $record;
    }
}
