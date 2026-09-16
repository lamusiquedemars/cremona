<?php

namespace App\Filament\Resources\IncomingRequests;

use App\Enums\IncomingRequestStatus;
use App\Enums\IncomingRequestUrgency;
use App\Filament\Concerns\UsesOrganizationPresentation;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Filament\Resources\IncomingRequests\Pages\ListIncomingRequests;
use App\Filament\Resources\IncomingRequests\Pages\ViewIncomingRequest;
use App\Models\IncomingRequest;
use App\Services\OrganizationPresentation;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class IncomingRequestResource extends Resource
{
    use UsesOrganizationPresentation;

    protected static ?string $model = IncomingRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Suivi client';

    protected static ?string $navigationLabel = 'Demandes';

    protected static ?string $presentationKey = 'requests';

    protected static string $organizationModule = 'crm';

    protected static ?string $presentationGroupKey = 'customer_follow_up';

    protected static ?string $modelLabel = 'demande';

    protected static ?string $pluralModelLabel = 'demandes';

    protected static ?string $recordTitleAttribute = 'subject';

    protected static ?int $navigationSort = 10;

    protected static bool $isGloballySearchable = true;

    protected static int $globalSearchResultsLimit = 10;

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'subject',
            'message',
            'name_snapshot',
            'email_snapshot',
            'phone_snapshot',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var IncomingRequest $record */
        return $record->subject
            ?? ($record->name_snapshot ? __('cremona.request.request_from', ['name' => $record->name_snapshot]) : __('cremona.request.untitled_request'));
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var IncomingRequest $record */
        return array_filter([
            __('cremona.navigation.items.contacts') => $record->name_snapshot ?? $record->email_snapshot ?? $record->phone_snapshot,
            __('cremona.crm.status') => $record->status->getLabel(),
            __('cremona.request.received') => $record->received_at?->format('d/m/Y H:i'),
        ]);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()
            ->where('status', IncomingRequestStatus::New)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'info';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make(fn (): string => app(OrganizationPresentation::class)->label('requests', 'Demande'))
                    ->columnSpan(2)
                    ->schema([
                        TextEntry::make('subject')
                            ->label(__('cremona.crm.subject'))
                            ->placeholder(__('cremona.crm.untitled'))
                            ->weight('semibold'),
                        TextEntry::make('message')
                            ->label(__('cremona.crm.message'))
                            ->columnSpanFull(),
                    ]),
                Section::make(__('cremona.request.processing'))
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('status')
                            ->label(__('cremona.crm.status'))
                            ->badge(),
                        TextEntry::make('urgency')
                            ->label(__('cremona.request.urgency'))
                            ->badge(),
                        TextEntry::make('assignedUser.name')
                            ->label(__('cremona.crm.assignee'))
                            ->placeholder(__('cremona.crm.unassigned')),
                        TextEntry::make('outcome')
                            ->label(__('cremona.request.outcome'))
                            ->badge()
                            ->placeholder('—'),
                    ]),
                Section::make(__('cremona.request.declared_contact'))
                    ->description(__('cremona.request.declared_contact_description'))
                    ->columnSpan(2)
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('name_snapshot')->label(__('cremona.request.last_name'))->placeholder('—'),
                            TextEntry::make('email_snapshot')->label(__('cremona.request.email'))->placeholder('—')->copyable(),
                            TextEntry::make('phone_snapshot')->label(__('cremona.request.phone'))->placeholder('—')->copyable(),
                            TextEntry::make('important_date')->label(__('cremona.request.important_date'))->date()->placeholder('—'),
                        ]),
                    ]),
                Section::make(__('cremona.request.crm_links'))
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('person.display_name')
                            ->label(fn (): string => app(OrganizationPresentation::class)->label('contacts', 'Contact'))
                            ->placeholder(__('cremona.crm.unlinked')),
                        TextEntry::make('company.name')
                            ->label(fn (): string => app(OrganizationPresentation::class)->label('companies', 'Entreprise'))
                            ->placeholder(__('cremona.crm.unlinked')),
                        TextEntry::make('source')
                            ->label(__('cremona.request.origin'))
                            ->formatStateUsing(fn (?string $state): string => static::sourceLabel($state))
                            ->placeholder('—'),
                        TextEntry::make('source_channel')
                            ->label(__('cremona.request.channel'))
                            ->badge(),
                        TextEntry::make('conversation.public_id')
                            ->label(fn (): string => app(OrganizationPresentation::class)->label('conversations', 'Correspondance'))
                            ->formatStateUsing(fn (): string => __('cremona.request.open_thread'))
                            ->url(fn (IncomingRequest $record): ?string => $record->conversation
                                ? ConversationResource::getUrl('view', ['record' => $record->conversation])
                                : null)
                            ->placeholder(__('cremona.request.none')),
                    ]),
                Section::make(__('cremona.request.acquisition'))
                    ->description(__('cremona.request.acquisition_description'))
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('attribution_source')->label(__('cremona.request.source'))->placeholder(__('cremona.request.unknown')),
                        TextEntry::make('attribution_medium')->label(__('cremona.request.medium'))->placeholder('—'),
                        TextEntry::make('attribution_campaign')->label(__('cremona.navigation.items.campaigns'))->placeholder('—'),
                        TextEntry::make('attribution_first_touch.landing_page')
                            ->label(__('cremona.request.first_landing_page'))
                            ->placeholder('—'),
                        TextEntry::make('attribution_last_touch.landing_page')
                            ->label(__('cremona.request.last_landing_page'))
                            ->placeholder('—'),
                        TextEntry::make('attribution_last_touch.utm_term')
                            ->label(__('cremona.request.declared_term'))
                            ->placeholder('—'),
                        TextEntry::make('attribution_method')->label(__('cremona.request.attribution_method'))->placeholder('—'),
                        TextEntry::make('attribution_confidence')
                            ->label(__('cremona.request.confidence'))
                            ->formatStateUsing(fn (?string $state): string => $state !== null
                                ? round((float) $state * 100).'%'
                                : '—'),
                        TextEntry::make('attribution_last_touch.gclid')
                            ->label(__('cremona.request.google_click_identifier'))
                            ->limit(32)
                            ->copyable()
                            ->placeholder('—'),
                    ]),
                Section::make(__('cremona.request.commercial_result'))
                    ->columnSpanFull()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('outcome')->label(__('cremona.request.outcome'))->badge()->placeholder('—'),
                        TextEntry::make('commercial_value')
                            ->label(__('cremona.request.assigned_value'))
                            ->money(fn (IncomingRequest $record): string => $record->commercial_currency ?? 'EUR')
                            ->placeholder(__('cremona.request.not_provided')),
                        TextEntry::make('converted_at')->label(__('cremona.request.conversion'))->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('lost_reason')->label(__('cremona.request.loss_reason'))->placeholder('—'),
                    ]),
                Section::make(__('cremona.request.additional_answers'))
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed(fn (IncomingRequest $record): bool => $record->answers->isEmpty())
                    ->schema([
                        RepeatableEntry::make('answers')
                            ->label('')
                            ->schema([
                                TextEntry::make('label_snapshot')->label(__('cremona.request.question'))->weight('medium'),
                                TextEntry::make('value')->label(__('cremona.request.answer'))->placeholder('—'),
                            ])
                            ->columns(2),
                    ]),
                Section::make(__('cremona.request.consents'))
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed(fn (IncomingRequest $record): bool => $record->consents->isEmpty())
                    ->schema([
                        RepeatableEntry::make('consents')
                            ->label('')
                            ->schema([
                                TextEntry::make('purpose')->label(__('cremona.request.purpose')),
                                TextEntry::make('channel')->label(__('cremona.request.channel')),
                                TextEntry::make('status')->label(__('cremona.crm.status'))->badge(),
                                TextEntry::make('statement_snapshot')->label(__('cremona.request.displayed_text'))->columnSpanFull(),
                            ])
                            ->columns(3),
                    ]),
                Section::make(__('cremona.request.history'))
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('activities')
                            ->label('')
                            ->schema([
                                TextEntry::make('event')
                                    ->label(__('cremona.request.event'))
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'received' => __('cremona.request.event_received'),
                                        'read' => __('cremona.request.event_read'),
                                        'status_changed' => __('cremona.request.event_status_changed'),
                                        'assigned' => __('cremona.request.event_assigned'),
                                        'person_linked' => __('cremona.request.event_person_linked'),
                                        'person_created_and_linked' => __('cremona.request.event_person_created'),
                                        'company_linked' => __('cremona.request.event_company_linked'),
                                        'note_added' => __('cremona.request.event_note_added'),
                                        default => $state,
                                    })
                                    ->badge(),
                                TextEntry::make('actor.name')->label(__('cremona.request.by'))->placeholder(__('cremona.request.system')),
                                TextEntry::make('body')->label(__('cremona.request.details'))->placeholder('—'),
                                TextEntry::make('recorded_at')->label(__('cremona.dashboard.date'))->dateTime('d/m/Y H:i'),
                            ])
                            ->columns(4),
                    ]),
            ]);
    }

    public static function sourceLabel(?string $source): string
    {
        return match ($source) {
            'maracuja-cms' => 'Formulaire du site',
            null, '' => '—',
            default => $source,
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('received_at', 'desc')
            ->columns([
                TextColumn::make('name_snapshot')
                    ->label(fn (): string => app(OrganizationPresentation::class)->label('contacts', 'Contact'))
                    ->description(fn (IncomingRequest $record): ?string => $record->email_snapshot ?? $record->phone_snapshot)
                    ->placeholder(__('cremona.dashboard.anonymous'))
                    ->searchable(['name_snapshot', 'email_snapshot', 'phone_snapshot'])
                    ->weight('medium'),
                TextColumn::make('subject')
                    ->label(fn (): string => app(OrganizationPresentation::class)->label('requests', 'Demande'))
                    ->description(fn (IncomingRequest $record): string => str($record->message)->squish()->limit(70))
                    ->placeholder(__('cremona.crm.untitled'))
                    ->searchable(['subject', 'message'])
                    ->wrap(),
                TextColumn::make('status')
                    ->label(__('cremona.crm.status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('urgency')
                    ->label(__('cremona.request.urgency'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('assignedUser.name')
                    ->label(__('cremona.crm.assignee'))
                    ->placeholder(__('cremona.crm.unassigned'))
                    ->toggleable(),
                TextColumn::make('received_at')
                    ->label(__('cremona.request.received'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('cremona.crm.status'))
                    ->options(IncomingRequestStatus::class),
                SelectFilter::make('urgency')
                    ->label(__('cremona.request.urgency'))
                    ->options(IncomingRequestUrgency::class),
                Filter::make('unread')
                    ->label(__('cremona.request.unread'))
                    ->query(fn (Builder $query): Builder => $query->whereNull('read_at')),
                Filter::make('unassigned')
                    ->label(__('cremona.request.unassigned'))
                    ->query(fn (Builder $query): Builder => $query->whereNull('assigned_user_id')),
            ])
            ->recordClasses(fn (IncomingRequest $record): ?string => $record->read_at === null ? 'crm-record-unread' : null)
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIncomingRequests::route('/'),
            'view' => ViewIncomingRequest::route('/{record}'),
        ];
    }
}
