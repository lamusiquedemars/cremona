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
            ->heading(__('cremona.dashboard.requests_heading'))
            ->description(__('cremona.dashboard.requests_description'))
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
                    ->label(__('cremona.dashboard.contact'))
                    ->placeholder(__('cremona.dashboard.anonymous'))
                    ->weight('medium'),
                TextColumn::make('subject')
                    ->label(__('cremona.dashboard.request'))
                    ->description(fn (IncomingRequest $record): string => str($record->message)->squish()->limit(60))
                    ->placeholder(__('cremona.dashboard.untitled'))
                    ->wrap(),
                TextColumn::make('status')
                    ->label(__('cremona.dashboard.status'))
                    ->badge(),
                TextColumn::make('assignedUser.name')
                    ->label(__('cremona.dashboard.assignee'))
                    ->placeholder(__('cremona.dashboard.unassigned')),
                TextColumn::make('received_at')
                    ->label(__('cremona.dashboard.received'))
                    ->since(),
            ])
            ->recordUrl(fn (IncomingRequest $record): string => IncomingRequestResource::getUrl('view', ['record' => $record]))
            ->recordClasses(fn (IncomingRequest $record): ?string => $record->read_at === null ? 'crm-record-unread' : null)
            ->headerActions([
                Action::make('seeAll')
                    ->label(__('cremona.dashboard.see_all_requests'))
                    ->icon(Heroicon::OutlinedArrowRight)
                    ->url(IncomingRequestResource::getUrl('index')),
            ])
            ->paginated(false);
    }
}
