<?php

namespace NineteenNinetyFour\Reviews;

use Statamic\Facades\Addon;
use Throwable;

/**
 * The addon's settings: what's saved in the Control Panel (Tools → Addons →
 * Reviews → Settings), falling back to config/reviews.php for anything
 * not saved there.
 */
class Settings
{
    public const ADDON = '1994/reviews-statamic';

    /** Settings fields and the config they fall back to. */
    protected const CONFIG = [
        'collections' => 'reviews.collections',
        'who' => 'reviews.form.who',
        'require_title' => 'reviews.form.require_title',
        'require_body' => 'reviews.form.require_body',
        'auto_approve' => 'reviews.auto_approve',
        'name_display' => 'reviews.name_display',
        'criteria' => 'reviews.criteria',
        'photos_enabled' => 'reviews.photos.enabled',
        'photos_max' => 'reviews.photos.max',
        'photos_container' => 'reviews.photos.container',
        'notify_to' => 'reviews.notifications.to',
        'notify_approved' => 'reviews.notifications.approved',
        'notify_replied' => 'reviews.notifications.replied',
        'buyers_only' => 'reviews.cargo.buyers_only',
        'requests_enabled' => 'reviews.cargo.requests.enabled',
        'requests_delay_days' => 'reviews.cargo.requests.delay_days',
        'requests_status' => 'reviews.cargo.requests.status',
        'request_subject' => null,
        'request_intro' => null,
    ];

    /** @var array<string, mixed>|null */
    protected static ?array $saved = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        $saved = static::saved();

        if (array_key_exists($key, $saved) && $saved[$key] !== null && $saved[$key] !== '') {
            return $saved[$key];
        }

        $config = self::CONFIG[$key] ?? null;

        return $config ? config($config, $default) : $default;
    }

    public static function bool(string $key): bool
    {
        return (bool) filter_var(static::get($key, false), FILTER_VALIDATE_BOOL);
    }

    public static function int(string $key, int $default, int $min = 0, int $max = PHP_INT_MAX): int
    {
        $value = static::get($key, $default);

        return is_numeric($value) ? max($min, min($max, (int) $value)) : $default;
    }

    public static function string(string $key, ?string $default = null): ?string
    {
        $value = static::get($key, $default);

        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }

    /**
     * @return array<int, string>
     */
    public static function list(string $key): array
    {
        $value = static::get($key, []);

        if (is_string($value)) {
            $value = preg_split('/[\s,;]+/', $value) ?: [];
        }

        return array_values(array_filter(array_map(fn ($item) => is_string($item) ? trim($item) : '', (array) $value)));
    }

    /**
     * Forget what was read, as when the settings are saved.
     */
    public static function flush(): void
    {
        static::$saved = null;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function saved(): array
    {
        if (static::$saved !== null) {
            return static::$saved;
        }

        try {
            $values = Addon::get(self::ADDON)?->settings()->raw();
        } catch (Throwable) {
            $values = null;
        }

        return static::$saved = is_array($values) ? $values : [];
    }
}
