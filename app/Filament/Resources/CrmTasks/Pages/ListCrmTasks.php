<?php

namespace App\Filament\Resources\CrmTasks\Pages;

use App\Enums\CrmTaskStatus;
use App\Filament\Resources\CrmTasks\CrmTaskResource;
use App\Models\CrmTask;
use App\Tenancy\OrganizationContext;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCrmTasks extends ListRecords
{
    protected static string $resource = CrmTaskResource::class;

    public function getTabs(): array
    {
        $now = now();
        $endOfDay = now(app(OrganizationContext::class)->require()->timezone())->endOfDay()->utc();

        return [
            'all' => Tab::make(__('common.all')),
            'due' => Tab::make(__('common.due'))
                ->badge(fn (): int => CrmTask::query()
                    ->whereIn('status', [CrmTaskStatus::Open, CrmTaskStatus::InProgress])
                    ->whereNotNull('due_at')
                    ->where('due_at', '<=', $endOfDay)
                    ->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereIn('status', [CrmTaskStatus::Open, CrmTaskStatus::InProgress])
                    ->whereNotNull('due_at')
                    ->where('due_at', '<=', $endOfDay)),
            'overdue' => Tab::make(__('common.overdue'))
                ->badge(fn (): int => CrmTask::query()
                    ->whereIn('status', [CrmTaskStatus::Open, CrmTaskStatus::InProgress])
                    ->where('due_at', '<', $now)
                    ->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereIn('status', [CrmTaskStatus::Open, CrmTaskStatus::InProgress])
                    ->where('due_at', '<', $now)),
            'open' => Tab::make(__('common.open_plural'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereIn('status', [CrmTaskStatus::Open, CrmTaskStatus::InProgress])),
            'completed' => Tab::make(__('common.completed_plural'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', CrmTaskStatus::Completed)),
        ];
    }
}
