<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['invoice_id', 'paid_on', 'amount', 'method', 'reference', 'notes', 'recorded_by_user_id'])]
class InvoicePayment extends Model
{
    use BelongsToOrganization;

    protected static function booted(): void
    {
        static::saving(function (self $payment): void {
            $invoice = Invoice::withoutGlobalScopes()->find($payment->invoice_id);
            if ($invoice === null || ! in_array($invoice->status, [\App\Enums\InvoiceStatus::Issued, \App\Enums\InvoiceStatus::PartiallyPaid, \App\Enums\InvoiceStatus::Overdue], true)) {
                throw new \LogicException('Un règlement ne peut être enregistré que sur une facture émise.');
            }
            if ((float) $payment->amount <= 0) {
                throw new \LogicException('Le montant d’un règlement doit être positif.');
            }
        });
        static::saved(fn (self $payment) => app(\App\Services\InvoiceWorkflowManager::class)->refreshPayments($payment->invoice));
        static::deleting(function (self $payment): void {
            $invoice = Invoice::withoutGlobalScopes()->find($payment->invoice_id);
            if ($invoice?->status === \App\Enums\InvoiceStatus::Paid) {
                throw new \LogicException('Un règlement confirmé ne peut pas être supprimé directement.');
            }
        });
        static::deleted(fn (self $payment) => app(\App\Services\InvoiceWorkflowManager::class)->refreshPayments($payment->invoice));
    }

    protected function casts(): array { return ['paid_on' => 'immutable_date', 'amount' => 'decimal:2']; }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function recordedBy(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by_user_id'); }
}
