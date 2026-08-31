<?php

namespace App\Filament\Widgets;

use App\Enums\CrmTaskStatus;
use App\Enums\OrganizationPermission;
use App\Filament\Resources\CrmTasks\CrmTaskResource;
use App\Models\CrmTask;
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
            && $user->hasOrganizationPermission(OrganizationPermission::ViewCrm, $organization);
    }

    public function table(Table $table): Table
    {
        $timezone = app(OrganizationContext::class)->require()->timezone();

        return $table
            ->heading('Tâches à faire')
            ->description('Les retards arrivent en premier, puis les prochaines échéances.')
            ->query(
                CrmTask::query()
                    ->whereIn('status', [CrmTaskStatus::Open, CrmTaskStatus::InProgress])
                    ->orderByRaw('due_at IS NULL')
                    ->orderBy('due_at')
                    ->limit(8),
            )
            ->columns([
                TextColumn::make('title')->label('Tâche')->weight('medium')->wrap(),
                TextColumn::make('priority')->label('Priorité')->badge(),
                TextColumn::make('due_at')
                    ->label('Échéance')
                    ->dateTime('d/m/Y H:i')
                    ->timezone($timezone)
                    ->color(fn (CrmTask $record): string => $record->due_at?->isPast() ? 'danger' : 'gray')
                    ->placeholder('Sans échéance'),
                TextColumn::make('assignedUser.name')->label('Responsable')->placeholder('Non attribué'),
            ])
            ->recordUrl(fn (CrmTask $record): string => CrmTaskResource::getUrl('view', ['record' => $record]))
            ->headerActions([
                Action::make('seeAll')
                    ->label('Voir toutes les tâches')
                    ->icon(Heroicon::OutlinedArrowRight)
                    ->url(CrmTaskResource::getUrl('index')),
            ])
            ->paginated(false);
    }
}
