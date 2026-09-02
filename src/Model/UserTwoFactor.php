<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\Model;

use Override;
use YiiRocks\Voyti\Model\User;
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\PrivatePropertiesTrait;

/**
 * ActiveRecord for the `user_two_factor` table: a user's two-factor state, held out of the core
 * `user` table so core carries no 2FA data. `secret` stores the method's shared secret or last
 * delivered code, `method` the active method name (its {@see TwoFactorMethodInterface::getName()}).
 */
final class UserTwoFactor extends ActiveRecord
{
    use PrivatePropertiesTrait;

    private bool $enabled = false;
    private ?string $method = null;
    private ?string $secret = null;
    private int $secret_attempts = 0;
    private ?int $secret_created_at = null;
    private int $user_id = 0;

    public static function findByUserId(int $userId): ?self
    {
        /** @var ?self */
        return self::query()->where(['user_id' => $userId])->one();
    }

    /**
     * The user's existing 2FA record, or a fresh unsaved one bound to that user.
     */
    public static function forUser(User $user): self
    {
        $userId = $user->getIdOrZero();
        $record = self::findByUserId($userId);
        if ($record !== null) {
            return $record;
        }

        $record = new self();
        $record->setUserId($userId);

        return $record;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function getSecretCreatedAt(): ?int
    {
        return $this->secret_created_at;
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return list{'user_id'}
     */
    #[Override]
    public function primaryKey(): array
    {
        return ['user_id'];
    }

    public function recordEmailAttempt(int $lifespan, int $maxAttempts): bool
    {
        $affected = $this->updateAllCounters(
            ['secret_attempts' => 1],
            [
                'and',
                ['user_id' => $this->user_id],
                ['>=', 'secret_created_at', time() - $lifespan],
                ['<', 'secret_attempts', $maxAttempts],
            ],
        );

        if ($affected !== 1) {
            return false;
        }

        $this->secret_attempts++;
        return true;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function setMethod(?string $method): void
    {
        $this->method = $method;
    }

    public function setSecret(?string $secret): void
    {
        $this->secret = $secret;
    }

    public function setSecretAttempts(int $attempts): void
    {
        $this->secret_attempts = $attempts;
    }

    public function setSecretCreatedAt(?int $createdAt): void
    {
        $this->secret_created_at = $createdAt;
    }

    public function setUserId(int $userId): void
    {
        $this->user_id = $userId;
    }
}
