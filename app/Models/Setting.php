<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public const CACHE_KEY = 'javapucino.settings';

    protected static function booted(): void
    {
        static::saved(fn () => static::flush());
        static::deleted(fn () => static::flush());
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** Seluruh pengaturan sebagai array key => value (di-cache). */
    public static function all_cached(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $defaults = config('javapucino.default_settings', []);

            try {
                $rows = static::pluck('value', 'key')->toArray();
            } catch (\Throwable $e) {
                $rows = [];
            }

            return array_merge($defaults, $rows);
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all_cached()[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }
}
