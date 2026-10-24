<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['position', 'source_type', 'source_id', 'description', 'quantity', 'unit', 'unit_amount', 'discount_amount', 'tax_rate', 'tax_category'])]
class InvoiceLine extends Model
{
    use BelongsToOrganization;

    protected static function booted(): void
    {
        static::saving(function (self $line): void {
            $invoice = Invoice::withoutGlobalScopes()->find($line->invoice_id);
            if ($invoice !== null && $invoice->status !== \App\Enums\InvoiceStatus::Draft) {
                throw new \LogicException('Les lignes d’une facture émise sont figées. Émettez un avoir si une correction est nécessaire.');
            }
            $net = max(0, round(((float) $line->quantity * (float) $line->unit_amount) - (float) $line->discount_amount, 2));
            $tax = round($net * ((float) $line->tax_rate / 100), 2);
            $line->net_amount = $net;
            $line->tax_amount = $tax;
            $line->total_amount = $net + $tax;
        });
        static::saved(fn (self $line) => app(\App\Services\InvoiceWorkflowManager::class)->refreshAmounts($line->invoice));
        static::deleting(function (self $line): void {
            $invoice = Invoice::withoutGlobalScopes()->find($line->invoice_id);
            if ($invoice !== null && $invoice->status !== \App\Enums\InvoiceStatus::Draft) {
                throw new \LogicException('Les lignes d’une facture émise sont figées.');
            }
        });
        static::deleted(fn (self $line) => app(\App\Services\InvoiceWorkflowManager::class)->refreshAmounts($line->invoice));
    }

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_amount' => 'decimal:2', 'discount_amount' => 'decimal:2', 'tax_rate' => 'decimal:2', 'net_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total_amount' => 'decimal:2'];
    }

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
}
