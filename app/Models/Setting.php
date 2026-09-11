<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // Dos claves: 'settings.all' la usa Setting::get() y 'settings.all_view'
        // el View composer de AppViewServiceProvider. Ambas son rememberForever,
        // así que olvidar solo una dejaba el front sirviendo valores viejos
        // (se notaba al editar metas o textos y no ver el cambio hasta un
        // cache:clear manual).
        $clear = function (): void {
            Cache::forget('settings.all');
            Cache::forget('settings.all_view');
        };
        static::saved($clear);
        static::deleted($clear);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever('settings.all', function () {
            return static::all()->mapWithKeys(fn ($s) => [$s->key => static::castValue($s->value, $s->type)])->all();
        });

        return $all[$key] ?? $default;
    }

    /**
     * Reads a locale-suffixed setting ("{$prefix}_{$locale}") with fallback
     * to the Spanish variant, mirroring the locale → es cascade used across
     * the site's localized SEO fields (see
     * App\Models\Concerns\HasLocalizedSeoMeta::schemaJsonLd() and
     * App\Support\PageSeo::value()). Returns null — never an empty/whitespace
     * string — when nothing is configured for either locale, so the caller
     * can tell "not configured" apart from "configured as blank".
     */
    public static function getLocalized(string $prefix, ?string $locale = null): ?string
    {
        $locale = $locale ?: app()->getLocale();

        foreach (array_unique([$locale, 'es']) as $candidate) {
            $value = static::get("{$prefix}_{$candidate}");

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    public static function set(string $key, mixed $value, string $type = 'string', string $group = 'general'): self
    {
        $stored = is_array($value) || is_object($value) ? json_encode($value) : (string) $value;

        return static::updateOrCreate(['key' => $key], ['value' => $stored, 'type' => $type, 'group' => $group]);
    }

    protected static function castValue(?string $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'float' => (float) $value,
            'array', 'json' => $value ? json_decode($value, true) : [],
            default => $value,
        };
    }
}
