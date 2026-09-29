<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Admin-tunable marketplace knobs (DIQ-805), e.g. sponsored slots per page. */
class MarketplaceSetting extends Model
{
    public const DEFAULTS = [
        'sponsored_slots_per_page' => 2,
        'homepage_slots' => 6,
    ];

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public static function get(string $key): mixed
    {
        return static::find($key)?->value ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /** @return array<string, mixed> */
    public static function allValues(): array
    {
        return array_replace(self::DEFAULTS, static::query()->pluck('value', 'key')->all());
    }
}
