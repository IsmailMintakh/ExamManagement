<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'type', 'label', 'description'];

    protected const CACHE_KEY = 'site_settings.all';
    protected const CACHE_TTL = 86400; // 24h — settings rarely change; we flush on save anyway

    /**
     * Get a setting value by key. Cached for the request.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::allCached();
        return $all[$key] ?? $default;
    }

    /**
     * Set a setting value (creates if missing) and bust the cache.
     */
    public static function put(string $key, mixed $value, array $attrs = []): self
    {
        $row = static::updateOrCreate(
            ['key' => $key],
            array_merge(['value' => is_array($value) ? json_encode($value) : (string) $value], $attrs)
        );
        static::flush();
        return $row;
    }

    /**
     * Bulk replace many settings at once.
     */
    public static function putMany(array $pairs): void
    {
        foreach ($pairs as $key => $value) {
            static::updateOrCreate(
                ['key' => $key],
                ['value' => is_array($value) ? json_encode($value) : (string) $value]
            );
        }
        static::flush();
    }

    /**
     * All settings as a flat key=>value array, cached.
     * JSON-typed rows are decoded automatically.
     */
    public static function allCached(): array
    {
        return Cache::remember(static::CACHE_KEY, static::CACHE_TTL, function () {
            return static::query()->get()->mapWithKeys(function ($row) {
                $value = $row->value;
                if ($row->type === 'json' && is_string($value) && $value !== '') {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) $value = $decoded;
                } elseif ($row->type === 'boolean') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                } elseif ($row->type === 'number') {
                    $value = is_numeric($value) ? $value + 0 : 0;
                }
                return [$row->key => $value];
            })->toArray();
        });
    }

    public static function flush(): void
    {
        Cache::forget(static::CACHE_KEY);
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flush());
        static::deleted(fn () => static::flush());
    }
}
