<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['workshop_order_service_id', 'quote_line_template_id', 'template_label_snapshot', 'kind', 'description', 'quantity', 'unit_amount'])]
class QuoteLine extends Model
{
    use BelongsToOrganization;

    protected static function booted(): void
    {
        static::creating(function (self $line): void {
            if ($line->position === null) {
                $line->position = ((int) self::query()
                    ->where('quote_id', $line->quote_id)
                    ->max('position')) + 1;
            }
        });
        static::saving(function (self $line): void {
            if ($line->quantity <= 0) {
                throw new LogicException('La quantité doit être positive.');
            } $line->total_amount = round((float) $line->quantity * (float) $line->unit_amount, 2);
        });
        static::saved(fn (self $line) => $line->quote->refreshAmounts());
        static::deleted(fn (self $line) => $line->quote->refreshAmounts());
    }

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'unit_amount' => 'decimal:2', 'total_amount' => 'decimal:2'];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(QuoteLineTemplate::class, 'quote_line_template_id');
    }
}
