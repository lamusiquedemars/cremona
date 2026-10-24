<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable(['kind', 'person_id', 'company_id', 'quote_id', 'workshop_order_id', 'rental_id', 'credit_note_for_invoice_id', 'issued_on', 'supply_on', 'due_on', 'currency', 'title', 'introduction', 'payment_terms', 'payment_instructions', 'late_payment_terms', 'discount_amount'])]
class Invoice extends Model
{
    use BelongsToOrganization;

    protected static function booted(): void
    {
        static::creating(function (self $invoice): void {
            $invoice->public_id ??= (string) Str::ulid();
            $invoice->status ??= InvoiceStatus::Draft;
            $invoice->currency ??= 'EUR';
        });

        static::saving(function (self $invoice): void {
            $invoice->title = trim($invoice->title);
            if ($invoice->title === '') {
                throw new LogicException('Une facture doit avoir un intitulé.');
            }
            if ($invoice->exists && $invoice->getRawOriginal('status') !== InvoiceStatus::Draft->value && $invoice->isDirty()) {
                throw new LogicException('Une facture émise ne peut pas être modifiée. Émettez un avoir si une correction est nécessaire.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issued_on' => 'immutable_date', 'supply_on' => 'immutable_date', 'due_on' => 'immutable_date',
            'issued_at' => 'immutable_datetime', 'sent_at' => 'immutable_datetime', 'paid_at' => 'immutable_datetime', 'voided_at' => 'immutable_datetime',
            'issuer_snapshot' => 'array', 'recipient_snapshot' => 'array', 'settings_snapshot' => 'array',
            'subtotal_amount' => 'decimal:2', 'discount_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'balance_amount' => 'decimal:2',
        ];
    }

    public function person(): BelongsTo { return $this->belongsTo(Person::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function quote(): BelongsTo { return $this->belongsTo(Quote::class); }
    public function workshopOrder(): BelongsTo { return $this->belongsTo(WorkshopOrder::class); }
    public function rental(): BelongsTo { return $this->belongsTo(Rental::class); }
    public function creditNoteFor(): BelongsTo { return $this->belongsTo(self::class, 'credit_note_for_invoice_id'); }
    public function pdfPrivateDocument(): BelongsTo { return $this->belongsTo(PrivateDocument::class, 'pdf_private_document_id'); }
    public function lines(): HasMany { return $this->hasMany(InvoiceLine::class)->orderBy('position'); }
    public function payments(): HasMany { return $this->hasMany(InvoicePayment::class)->orderByDesc('paid_on')->orderByDesc('id'); }
    public function transmissions(): HasMany { return $this->hasMany(InvoiceTransmission::class)->latest(); }

    public function getRouteKeyName(): string { return 'public_id'; }
}
