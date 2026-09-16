<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use App\Services\QuoteNumberGenerator;
use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable(['reference', 'pennylane_quote_id', 'pennylane_status', 'pennylane_pdf_url', 'pennylane_synced_at', 'pennylane_last_error', 'person_id', 'company_id', 'incoming_request_id', 'workshop_order_id', 'title', 'status', 'currency', 'issued_on', 'valid_until', 'introduction', 'discount_amount', 'tax_note', 'payment_terms', 'notes', 'sent_via', 'issuer_snapshot', 'recipient_snapshot'])]
class Quote extends Model
{
    use BelongsToOrganization;

    protected static function booted(): void
    {
        static::creating(function (self $quote): void {
            $quote->public_id ??= (string) Str::ulid();
            if (blank($quote->reference)) {
                $quote->reference = app(QuoteNumberGenerator::class)->next((int) $quote->organization_id);
            }
            $settings = OrganizationQuoteSettings::query()->first();
            $quote->issued_on ??= now()->toDateString();
            $quote->valid_until ??= $settings?->default_validity_days ? now()->addDays($settings->default_validity_days)->toDateString() : null;
            $quote->payment_terms ??= $settings?->default_payment_terms;
            $quote->tax_note ??= $settings?->default_tax_note;
        });
        static::saving(function (self $quote): void {
            $quote->title = trim($quote->title);
            if ($quote->title === '') {
                throw new LogicException('Un devis doit avoir un intitulé.');
            }
            foreach ([Person::class => $quote->person_id, Company::class => $quote->company_id, IncomingRequest::class => $quote->incoming_request_id] as $model => $id) {
                if ($id !== null && ! $model::query()->whereKey($id)->exists()) {
                    throw new LogicException('Le rattachement du devis ne relève pas de l’organisation active.');
                }
            }
            $quote->sent_at = $quote->status === QuoteStatus::Sent ? ($quote->sent_at ?? now()) : $quote->sent_at;
            $quote->accepted_at = $quote->status === QuoteStatus::Accepted ? ($quote->accepted_at ?? now()) : null;
            $quote->declined_at = $quote->status === QuoteStatus::Declined ? ($quote->declined_at ?? now()) : null;
        });
    }

    protected function casts(): array
    {
        return ['status' => QuoteStatus::class, 'issued_on' => 'immutable_date', 'valid_until' => 'immutable_date', 'sent_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime', 'declined_at' => 'immutable_datetime', 'pennylane_synced_at' => 'immutable_datetime', 'discount_amount' => 'decimal:2', 'subtotal_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'issuer_snapshot' => 'array', 'recipient_snapshot' => 'array'];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function incomingRequest(): BelongsTo
    {
        return $this->belongsTo(IncomingRequest::class);
    }

    public function workshopOrder(): BelongsTo
    {
        return $this->belongsTo(WorkshopOrder::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)->orderBy('position');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function refreshAmounts(): void
    {
        $subtotal = (float) $this->lines()->sum('total_amount');
        $this->forceFill(['subtotal_amount' => $subtotal, 'total_amount' => max(0, $subtotal - (float) $this->discount_amount)])->saveQuietly();
    }
}
