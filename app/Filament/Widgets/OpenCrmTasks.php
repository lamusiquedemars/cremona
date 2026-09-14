<?php

namespace App\Filament\Widgets;

use App\Enums\CrmTaskStatus;
use App\Enums\OrganizationPermission;
use App\Filament\Resources\CrmTasks\CrmTaskResource;
use App\Models\CrmTask;
use App\Services\OrganizationModuleAccess;
use App\Tenancy\OrganizationContext;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class OpenCrmTasks extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 35;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $organization = app(OrganizationContext::class)->current();
        $user = auth()->user();

        return $organization !== null
            && $user !== null
            && app(OrganizationModuleAccess::class)->enabled('crm', $organization)
            && $user->hasOrganizationPermission(OrganizationPermission::ViewCrm, $organization);
    }

    public function table(Table $table): Table
    {
        $timezone = app(OrganizationContext::class)->require()->timezone();

        return $table
            ->heading(__('cremona.dashboard.tasks_heading'))
            ->description(__('cremona.dashboard.tasks_description'))
            ->query(
                CrmTask::query()
                    ->whereIn('status', [CrmTaskStatus::Open, CrmTaskStatus::InProgress])
                    ->orderByRaw('due_at IS NULL')
                    ->orderBy('due_at')
                    ->limit(8),
            )
            ->columns([
                TextColumn::make('title')->label(__('cremona.dashboard.task'))->weight('medium')->wrap(),
                TextColumn::make('priority')->label(__('cremona.dashboard.priority'))->badge(),
                TextColumn::make('due_at')
                    ->label(__('cremona.dashboard.due_date'))
                    ->dateTime('d/m/Y H:i')
                    ->timezone($timezone)
                    ->color(fn (CrmTask $record): string => $record->due_at?->isPast() ? 'danger' : 'gray')
                    ->placeholder(__('cremona.dashboard.no_due_date')),
                TextColumn::make('assignedUser.name')->label(__('cremona.dashboard.assignee'))->placeholder(__('cremona.dashboard.unassigned')),
            ])
            ->recordUrl(fn (CrmTask $record): string => CrmTaskResource::getUrl('view', ['record' => $record]))
            ->headerActions([
                Action::make('seeAll')
                    ->label(__('cremona.dashboard.see_all_tasks'))
                    ->icon(Heroicon::OutlinedArrowRight)
                    ->url(CrmTaskResource::getUrl('index')),
            ])
            ->paginated(false);
    }
}
