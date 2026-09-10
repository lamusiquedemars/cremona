<?php

namespace App\Filament\Widgets;

use App\Enums\IncomingRequestStatus;
use App\Enums\OrganizationPermission;
use App\Filament\Resources\IncomingRequests\IncomingRequestResource;
use App\Models\IncomingRequest;
use App\Services\OrganizationModuleAccess;
use App\Tenancy\OrganizationContext;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ActiveIncomingRequests extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 20;

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
        return $table
            ->heading('Demandes à traiter')
            ->description('Les demandes nouvelles, en cours ou qualifiées qui nécessitent une suite.')
            ->query(
                IncomingRequest::query()
                    ->whereIn('status', [
                        IncomingRequestStatus::New,
                        IncomingRequestStatus::InProgress,
                        IncomingRequestStatus::Qualified,
                    ])
                    ->orderByRaw('read_at IS NULL DESC')
                    ->latest('received_at')
                    ->limit(8),
            )
            ->columns([
                TextColumn::make('name_snapshot')
                    ->label('Contact')
                    ->placeholder('Anonyme')
                    ->weight('medium'),
                TextColumn::make('subject')
                    ->label('Demande')
                    ->description(fn (IncomingRequest $record): string => str($record->message)->squish()->limit(60))
                    ->placeholder('Sans objet')
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),
                TextColumn::make('assignedUser.name')
                    ->label('Responsable')
                    ->placeholder('Non attribuée'),
                TextColumn::make('received_at')
                    ->label('Reçue')
                    ->since(),
            ])
            ->recordUrl(fn (IncomingRequest $record): string => IncomingRequestResource::getUrl('view', ['record' => $record]))
            ->recordClasses(fn (IncomingRequest $record): ?string => $record->read_at === null ? 'crm-record-unread' : null)
            ->headerActions([
                Action::make('seeAll')
                    ->label('Voir toutes les demandes')
                    ->icon(Heroicon::OutlinedArrowRight)
                    ->url(IncomingRequestResource::getUrl('index')),
            ])
            ->paginated(false);
    }
}
