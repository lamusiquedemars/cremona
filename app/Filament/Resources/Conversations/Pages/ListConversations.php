<?php

namespace App\Filament\Resources\Conversations\Pages;

use App\Enums\ConversationStatus;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Models\Conversation;
use App\Models\EmailMailbox;
use App\Services\EmailMailboxSynchronizer;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class ListConversations extends ListRecords
{
    protected static string $resource = ConversationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_email')
                ->label('Relever les emails')
                ->icon('heroicon-o-arrow-path')
                ->action(function (): void {
                    $mailboxes = EmailMailbox::query()->where('status', 'active')->get();
                    if ($mailboxes->isEmpty()) {
                        Notification::make()
                            ->title('Aucune boîte email active')
                            ->warning()
                            ->send();

                        return;
                    }

                    $imported = 0;
                    $skipped = 0;
                    try {
                        foreach ($mailboxes as $mailbox) {
                            $result = app(EmailMailboxSynchronizer::class)->sync($mailbox);
                            $imported += $result['imported'];
                            $skipped += $result['skipped'];
                        }
                    } catch (Throwable $exception) {
                        report($exception);
                        Notification::make()
                            ->title('La relève a échoué')
                            ->body('Le détail est visible dans Boîtes email.')
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Relève terminée')
                        ->body("{$imported} message(s) importé(s), {$skipped} déjà connu(s).")
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Toutes'),
            'open' => Tab::make('À traiter')
                ->badge(fn (): int => $this->count(ConversationStatus::Open))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ConversationStatus::Open)),
            'waiting' => Tab::make('En attente du client')
                ->badge(fn (): int => $this->count(ConversationStatus::WaitingCustomer))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ConversationStatus::WaitingCustomer)),
            'closed' => Tab::make('Clôturées')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ConversationStatus::Closed)),
        ];
    }

    private function count(ConversationStatus $status): int
    {
        return Conversation::query()->where('status', $status)->count();
    }
}
