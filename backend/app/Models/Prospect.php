<?php

namespace App\Models;

use App\Support\Phone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A school or independent trainer being brought on board (DIQ-1103). Admin-only data. */
class Prospect extends Model
{
    public const TYPES = ['school', 'trainer'];

    public const STAGES = ['new', 'contacted', 'replied', 'claimed', 'lost', 'do_not_contact'];

    public const SOURCES = ['manual', 'csv', 'ads', 'gbp', 'outreach'];

    /** Mirrors the column defaults. */
    protected $attributes = ['type' => 'school', 'stage' => 'new', 'source' => 'manual'];

    protected $fillable = [
        'type', 'name', 'contact_person', 'phone', 'email', 'website', 'locality_id', 'address',
        'latitude', 'longitude', 'google_place_id', 'notes', 'source', 'stage', 'owner_admin_id', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'last_contacted_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Prospect $p) {
            $p->phone_e164 = self::normalPhone($p->phone);
            $p->email_normalized = self::normalEmail($p->email);
        });
    }

    public static function normalPhone(?string $phone): ?string
    {
        return Phone::toE164($phone) ?? (($d = preg_replace('/\D+/', '', (string) $phone)) !== '' ? $d : null);
    }

    public static function normalEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    /** An existing prospect with the same phone, email or Google place id. */
    public static function findDuplicate(?string $phone, ?string $email, ?string $placeId, ?int $ignoreId = null): ?self
    {
        $phone = self::normalPhone($phone);
        $email = self::normalEmail($email);
        if (! $phone && ! $email && ! $placeId) {
            return null;
        }

        return self::query()
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->where(function ($q) use ($phone, $email, $placeId) {
                if ($phone) {
                    $q->orWhere('phone_e164', $phone);
                }
                if ($email) {
                    $q->orWhere('email_normalized', $email);
                }
                if ($placeId) {
                    $q->orWhere('google_place_id', $placeId);
                }
            })
            ->first();
    }

    public function isContactable(): bool
    {
        return ! in_array($this->stage, ['claimed', 'lost', 'do_not_contact'], true);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function locality(): BelongsTo
    {
        return $this->belongsTo(Locality::class);
    }

    public function ownerAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_admin_id');
    }
}
