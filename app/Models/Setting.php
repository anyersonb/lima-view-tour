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
        $clear = fn () => Cache::forget('settings.all');
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
