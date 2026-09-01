<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\TwoFactor\Model;

use Override;
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\PrivatePropertiesTrait;

/**
 * ActiveRecord for the `user_backup_code` table: a two-factor-authentication backup code hash
 * that can be consumed once, tracked via `used_at`.
 */
final class UserBackupCode extends ActiveRecord
{
    use PrivatePropertiesTrait;

    private string $code_hash = '';
    private int $created_at = 0;
    private ?int $used_at = null;
    private int $user_id = 0;

    public static function deleteAllByUserId(int $userId): void
    {
        (new self())->deleteAll(['user_id' => $userId]);
    }

    /**
     * @return list<UserBackupCode>
     */
    public static function findUnusedByUserId(int $userId): array
    {
        /** @var list<UserBackupCode> $codes */
        $codes = self::query()->where(['user_id' => $userId, 'used_at' => null])->all();
        return $codes;
    }

    public function getCodeHash(): string
    {
        return $this->code_hash;
    }

    public function getCreatedAt(): int
    {
        return $this->created_at;
    }

    public function getUsedAt(): ?int
    {
        return $this->used_at;
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    /**
     * Atomically marks this backup code as used, conditioned on it still being unused at the time
     * of the write. Returns `false` instead of consuming it again if a concurrent request already
     * used it between the caller's read and this call.
     */
    public function markUsed(): bool
    {
        $usedAt = time();
        $affected = $this->updateAll(
            ['used_at' => $usedAt],
            ['user_id' => $this->user_id, 'code_hash' => $this->code_hash, 'used_at' => null],
        );

        if ($affected !== 1) {
            return false;
        }

        $this->used_at = $usedAt;
        return true;
    }

    /**
     * @return list{'user_id', 'code_hash'}
     */
    #[Override]
    public function primaryKey(): array
    {
        return ['user_id', 'code_hash'];
    }

    public function setCodeHash(string $codeHash): void
    {
        $this->code_hash = $codeHash;
    }

    public function setCreatedAt(int $createdAt): void
    {
        $this->created_at = $createdAt;
    }

    public function setUsedAt(?int $usedAt): void
    {
        $this->used_at = $usedAt;
    }

    public function setUserId(int $userId): void
    {
        $this->user_id = $userId;
    }
}
