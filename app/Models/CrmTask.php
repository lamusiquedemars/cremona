<?php

namespace App\Models;

use App\Enums\CrmTaskPriority;
use App\Enums\CrmTaskStatus;
use App\Tenancy\Concerns\BelongsToOrganization;
use App\Tenancy\Concerns\ValidatesOrganizationAssignee;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'assigned_user_id', 'person_id', 'company_id', 'incoming_request_id', 'conversation_id',
    'title', 'description', 'status', 'priority', 'due_at', 'completed_at',
])]
class CrmTask extends Model
{
    use BelongsToOrganization, ValidatesOrganizationAssignee;

    protected static function booted(): void
    {
        static::creating(function (self $task): void {
            $task->public_id ??= (string) Str::ulid();
        });

        static::saving(function (self $task): void {
            $task->title = trim($task->title);
            $task->description = filled($task->description) ? trim($task->description) : null;

            if ($task->title === '') {
                throw new LogicException('Une tâche doit avoir un intitulé.');
            }

            $task->completed_at = $task->status === CrmTaskStatus::Completed
                ? ($task->completed_at ?? now())
                : null;

            foreach ([
                Person::class => $task->person_id,
                Company::class => $task->company_id,
                IncomingRequest::class => $task->incoming_request_id,
                Conversation::class => $task->conversation_id,
            ] as $model => $id) {
                if ($id !== null && ! $model::query()->whereKey($id)->exists()) {
                    throw new LogicException('Le rattachement de la tâche ne relève pas de l’organisation active.');
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => CrmTaskStatus::class,
            'priority' => CrmTaskPriority::class,
            'due_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
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

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
