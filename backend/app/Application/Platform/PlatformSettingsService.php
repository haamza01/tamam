<?php

namespace App\Application\Platform;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;

class PlatformSettingsService
{
    private const CACHE_KEY = 'platform_settings.all';

    /** @var array<string, mixed>|null */
    private static ?array $memoryCache = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        if (! array_key_exists($key, $settings)) {
            return $default;
        }

        return $settings[$key];
    }

    public function getBool(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }

    public function getInt(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    public function set(string $key, mixed $value, ?string $group = null, ?string $description = null): PlatformSetting
    {
        $setting = PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) ? $value : ['value' => $value],
                'group' => $group,
                'description' => $description,
            ],
        );

        $this->flushCache();

        return $setting;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if (self::$memoryCache !== null) {
            return self::$memoryCache;
        }

        try {
            /** @var array<string, mixed> $cached */
            $cached = Cache::rememberForever(self::CACHE_KEY, fn (): array => $this->loadFromDatabase());

            return self::$memoryCache = $cached;
        } catch (\Throwable) {
            return self::$memoryCache = $this->loadFromDatabase();
        }
    }

    public function flushCache(): void
    {
        self::$memoryCache = null;

        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable) {
            // Cache backend may be unavailable in some local environments.
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFromDatabase(): array
    {
        return PlatformSetting::query()
            ->pluck('value', 'key')
            ->map(function (mixed $value): mixed {
                if (is_array($value) && array_key_exists('value', $value) && count($value) === 1) {
                    return $value['value'];
                }

                return $value;
            })
            ->all();
    }
}
