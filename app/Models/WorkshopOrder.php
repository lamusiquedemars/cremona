<?php

namespace App\Models;

use App\Enums\WorkshopOrderStatus;
use App\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

#[Fillable(['reference', 'person_id', 'incoming_request_id', 'title', 'instrument_description', 'customer_instructions', 'diagnosis', 'status', 'received_at', 'diagnosed_at', 'authorized_at', 'authorization_note', 'due_at', 'scheduled_at', 'started_at', 'ready_at', 'returned_at', 'notes'])]
class WorkshopOrder extends Model
{
    use BelongsToOrganization;

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            $order->public_id ??= (string) Str::ulid();
            $order->reference ??= 'AT-'.str($order->public_id)->substr(0, 8);
            $order->received_at ??= now();
        });

        static::saving(function (self $order): void {
            $order->title = trim($order->title);
            if ($order->title === '') {
                throw new LogicException('Un dossier atelier doit avoir un intitulé.');
            }
            foreach ([Person::class => $order->person_id, IncomingRequest::class => $order->incoming_request_id] as $model => $id) {
                if ($id !== null && ! $model::query()->whereKey($id)->exists()) {
                    throw new LogicException('Le rattachement du dossier atelier ne relève pas de l’organisation active.');
                }
            }
            $order->ready_at = $order->status === WorkshopOrderStatus::Ready ? ($order->ready_at ?? now()) : $order->ready_at;
            $order->returned_at = $order->status === WorkshopOrderStatus::Returned ? ($order->returned_at ?? now()) : $order->returned_at;
        });
    }

    protected function casts(): array
    {
        return ['status' => WorkshopOrderStatus::class, 'received_at' => 'immutable_datetime', 'diagnosed_at' => 'immutable_datetime', 'authorized_at' => 'immutable_datetime', 'due_at' => 'immutable_datetime', 'scheduled_at' => 'immutable_datetime', 'started_at' => 'immutable_datetime', 'ready_at' => 'immutable_datetime', 'returned_at' => 'immutable_datetime'];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function incomingRequest(): BelongsTo
    {
        return $this->belongsTo(IncomingRequest::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(WorkshopOrderService::class);
    }

    public function stockItems(): HasMany
    {
        return $this->hasMany(WorkshopOrderStockItem::class);
    }

    public function quote(): HasOne
    {
        return $this->hasOne(Quote::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
