<?php

namespace App\Filament\Widgets;

use App\Enums\ConversationStatus;
use App\Enums\OrganizationPermission;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Models\Conversation;
use App\Services\OrganizationModuleAccess;
use App\Tenancy\OrganizationContext;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ActiveConversations extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 25;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $organization = app(OrganizationContext::class)->current();
        $user = auth()->user();

        return $organization !== null
            && $user !== null
            && app(OrganizationModuleAccess::class)->enabled('crm', $organization)
            && Conversation::query()
                ->where('status', ConversationStatus::Open)
                ->whereNotNull('last_inbound_at')
                ->where(fn (Builder $query): Builder => $query
                    ->whereNull('last_outbound_at')
                    ->orWhereColumn('last_inbound_at', '>', 'last_outbound_at'))
                ->exists()
            && $user->hasOrganizationPermission(OrganizationPermission::ViewCorrespondence, $organization);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Correspondances à traiter')
            ->description('Les échanges dont le dernier message vient du contact.')
            ->query(
                Conversation::query()
                    ->where('status', ConversationStatus::Open)
                    ->whereNotNull('last_inbound_at')
                    ->where(fn (Builder $query): Builder => $query
                        ->whereNull('last_outbound_at')
                        ->orWhereColumn('last_inbound_at', '>', 'last_outbound_at'))
                    ->orderByDesc('last_inbound_at')
                    ->limit(8),
            )
            ->columns([
                TextColumn::make('subject')
                    ->label('Conversation')
                    ->placeholder('Sans objet')
                    ->weight('medium')
                    ->wrap(),
                TextColumn::make('person.display_name')
                    ->label('Contact')
                    ->placeholder('Non rattaché'),
                TextColumn::make('assignedUser.name')
                    ->label('Responsable')
                    ->placeholder('Non attribué')
                    ->hiddenFrom('md'),
                TextColumn::make('last_inbound_at')
                    ->label('Dernier message reçu')
                    ->since(),
            ])
            ->recordUrl(fn (Conversation $record): string => ConversationResource::getUrl('view', ['record' => $record]))
            ->headerActions([
                Action::make('seeAll')
                    ->label('Voir toutes les correspondances')
                    ->icon(Heroicon::OutlinedArrowRight)
                    ->url(ConversationResource::getUrl('index')),
            ])
            ->paginated(false);
    }
}
