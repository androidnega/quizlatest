<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Crypt;

class SystemSettingsService
{
    public function get(string $key, ?string $default = null): ?string
    {
        $row = SystemSetting::query()->where('key', $key)->first();
        if ($row === null || $row->value === null || $row->value === '') {
            return $default;
        }

        try {
            return Crypt::decryptString($row->value);
        } catch (\Throwable) {
            return $default;
        }
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $v = $this->get($key);
        if ($v === null || $v === '') {
            return $default;
        }

        return in_array(strtolower(trim($v)), ['1', 'true', 'yes', 'on'], true);
    }

    public function getInt(string $key, int $default): int
    {
        $v = $this->get($key);
        if ($v === null || $v === '') {
            return $default;
        }
        if (! is_numeric($v)) {
            return $default;
        }

        $i = (int) $v;

        return $i < 0 ? $default : $i;
    }

    /**
     * Masked placeholder for UI — never returns raw secrets.
     */
    public function getMasked(string $key): ?string
    {
        $row = SystemSetting::query()->where('key', $key)->first();
        if ($row === null || $row->value === null || $row->value === '') {
            return null;
        }

        return '********';
    }

    public function isLocked(string $key): bool
    {
        $row = SystemSetting::query()->where('key', $key)->first();

        return $row?->is_locked ?? false;
    }

    public function set(string $key, string $value, User $user): void
    {
        $existing = SystemSetting::query()->where('key', $key)->first();

        if ($existing && $existing->is_locked && $user->role !== 'admin') {
            throw new AuthorizationException('This setting is locked and cannot be modified.');
        }

        SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => Crypt::encryptString($value),
                'is_locked' => $existing?->is_locked ?? false,
            ],
        );
    }

    public function lockSetting(string $key, User $user): void
    {
        abort_unless($user->role === 'admin', 403);

        $row = SystemSetting::query()->where('key', $key)->first();
        if ($row === null) {
            return;
        }
        $row->update(['is_locked' => true]);
    }

    public function unlockSetting(string $key, User $user): void
    {
        abort_unless($user->role === 'admin', 403);

        SystemSetting::query()->where('key', $key)->update(['is_locked' => false]);
    }
}
