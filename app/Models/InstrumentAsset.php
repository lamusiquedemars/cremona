<?php

namespace App\Models;

use App\Enums\InstrumentAssetStatus;
use App\Services\InstrumentMediaAuditLogger;
use App\Services\InstrumentRentalPricing;
use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class InstrumentAsset extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    /** @var array<int, mixed> */
    private array $mediaBeforeUpdate = [];

    protected static function booted(): void
    {
        static::saving(function (self $instrument): void {
            if ($instrument->is_site_published && blank($instrument->public_slug)) {
                $instrument->public_slug = Str::slug($instrument->public_title ?: $instrument->name);
            }

            if ($instrument->rental_pricing_mode !== 'override') {
                $instrument->rental_amount_override = null;
            }

            app(InstrumentRentalPricing::class)->applyToInstrument($instrument);
        });

        static::updating(function (self $instrument): void {
            if ($instrument->isDirty('media')) {
                $instrument->mediaBeforeUpdate = self::decodeMedia($instrument->getRawOriginal('media'));
            }
        });

        static::created(function (self $instrument): void {
            $actor = auth()->user();
            app(InstrumentMediaAuditLogger::class)->recordChanges($instrument, [], $instrument->media ?? [], $actor instanceof User ? $actor : null);
        });

        static::updated(function (self $instrument): void {
            if ($instrument->wasChanged('media')) {
                $actor = auth()->user();
                app(InstrumentMediaAuditLogger::class)->recordChanges($instrument, $instrument->mediaBeforeUpdate, $instrument->media ?? [], $actor instanceof User ? $actor : null);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => InstrumentAssetStatus::class,
            'available_for_sale' => 'boolean',
            'available_for_rental' => 'boolean',
            'is_site_published' => 'boolean',
            'site_published_at' => 'immutable_datetime',
            'site_last_published_at' => 'immutable_datetime',
            'suggested_sale_amount' => 'decimal:2',
            'suggested_rental_amount' => 'decimal:2',
            'rental_amount_override' => 'decimal:2',
            'attributes' => 'array',
            'media' => 'array',
        ];
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    public function mediaAuditLogs(): HasMany
    {
        return $this->hasMany(OrganizationAuditLog::class, 'subject_id')
            ->where('subject_type', $this->getMorphClass())
            ->whereIn('event', ['instrument_media.photo_added', 'instrument_media.photo_removed', 'instrument_media.photo_updated'])
            ->latest('created_at');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InstrumentCategory::class, 'instrument_category_id');
    }

    public function rentalTier(): BelongsTo
    {
        return $this->belongsTo(RentalTier::class);
    }

    public function rentalMonthlyAmount(): float
    {
        if ($this->rental_pricing_mode === 'override') {
            return (float) ($this->rental_amount_override ?? 0);
        }

        return $this->category?->monthlyAmountOn() ?? 0.0;
    }

    /** @return array<int, mixed> */
    private static function decodeMedia(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
