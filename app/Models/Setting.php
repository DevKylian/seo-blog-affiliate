<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $guarded = [];

    protected $casts = ['value' => 'encrypted', 'is_secret' => 'boolean'];

    public static function value(string $key, mixed $default = null): mixed
    {
        return static::query()->where('key', $key)->first()?->value ?? $default;
    }

    public static function put(string $key, mixed $value, bool $secret = false): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value, 'is_secret' => $secret]);
    }
}
