<?php

namespace App\Filament\Resources\IncomingRequests\Pages;

use App\Enums\IncomingRequestStatus;
use App\Filament\Resources\IncomingRequests\IncomingRequestResource;
use App\Models\IncomingRequest;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListIncomingRequests extends ListRecords
{
    protected static string $resource = IncomingRequestResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('common.all'))
                ->icon(Heroicon::OutlinedInboxStack),
            'new' => Tab::make(__('common.new_plural'))
                ->icon(Heroicon::OutlinedSparkles)
                ->badge(fn (): int => $this->countRequests(
                    fn (Builder $query): Builder => $query->where('status', IncomingRequestStatus::New),
                ))
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', IncomingRequestStatus::New)),
            'mine' => Tab::make(__('common.assigned_to_me'))
                ->icon(Heroicon::OutlinedUserCircle)
                ->badge(fn (): int => $this->countRequests(
                    fn (Builder $query): Builder => $query
                        ->where('assigned_user_id', auth()->id())
                        ->where('status', '!=', IncomingRequestStatus::Closed),
                ))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('assigned_user_id', auth()->id())
                    ->where('status', '!=', IncomingRequestStatus::Closed)),
            'unassigned' => Tab::make(__('common.unassigned_plural'))
                ->icon(Heroicon::OutlinedUserMinus)
                ->badge(fn (): int => $this->countRequests(
                    fn (Builder $query): Builder => $query
                        ->whereNull('assigned_user_id')
                        ->where('status', '!=', IncomingRequestStatus::Closed),
                ))
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereNull('assigned_user_id')
                    ->where('status', '!=', IncomingRequestStatus::Closed)),
            'waiting' => Tab::make(__('common.waiting'))
                ->icon(Heroicon::OutlinedClock)
                ->badge(fn (): int => $this->countRequests(
                    fn (Builder $query): Builder => $query->where('status', IncomingRequestStatus::WaitingCustomer),
                ))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', IncomingRequestStatus::WaitingCustomer)),
            'closed' => Tab::make(__('common.closed_plural'))
                ->icon(Heroicon::OutlinedArchiveBox)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', IncomingRequestStatus::Closed)),
        ];
    }

    private function countRequests(callable $scope): int
    {
        return $scope(IncomingRequest::query())->count();
    }
}
