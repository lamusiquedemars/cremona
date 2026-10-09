<?php

namespace App\Filament\Resources\Rentals;

use App\Enums\RentalAcceptanceStatus;
use App\Enums\RentalStatus;
use App\Filament\Concerns\UsesOrganizationPresentation;
use App\Filament\Resources\Rentals\Pages\CreateRental;
use App\Filament\Resources\Rentals\Pages\EditRental;
use App\Filament\Resources\Rentals\Pages\ListRentals;
use App\Models\InstrumentAsset;
use App\Models\InstrumentCategory;
use App\Models\Rental;
use App\Models\RentalAcceptanceRequest;
use App\Models\RentalInsurancePlan;
use App\Services\InstrumentRentalPricing;
use App\Services\RentalInsurancePricing;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RentalResource extends Resource
{
    use UsesOrganizationPresentation;

    protected static ?string $model = Rental::class;

    protected static ?string $navigationLabel = 'Locations';

    protected static ?string $modelLabel = 'location';

    protected static ?string $pluralModelLabel = 'locations';

    protected static ?string $presentationGroupKey = 'workshop';

    protected static ?string $presentationKey = 'rentals';

    protected static string $organizationModule = 'rentals';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = 'Atelier';

    protected static ?int $navigationSort = 61;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(12)->components([
            Section::make('Accord du client')
                ->visible(fn (?Rental $record): bool => $record !== null)
                ->schema([
                    Placeholder::make('acceptance_status')
                        ->label('Situation')
                        ->content(fn (?Rental $record): string => self::acceptanceLabel($record?->latestAcceptance) ?? 'Aucune demande envoyée'),
                    Placeholder::make('acceptance_recipient')
                        ->label('Destinataire')
                        ->content(fn (?Rental $record): string => $record?->latestAcceptance?->recipient_email ?? '—'),
                    Placeholder::make('acceptance_timing')
                        ->label('Prochaine étape')
                        ->content(fn (?Rental $record): string => self::acceptanceTiming($record?->latestAcceptance)),
                ])->columns(3)->columnSpanFull(),
            Section::make('Location')->schema([
                TextInput::make('reference')->label(__('common.reference'))->helperText('Générée automatiquement si laissée vide.')->columnSpan(3),
                Select::make('status')->label(__('common.status'))->options(RentalStatus::class)->default(RentalStatus::Draft)->disabled()->dehydrated()->required()->columnSpan(3),
                Select::make('instrument_asset_id')->label('Instrument')->relationship('instrument', 'name')->getOptionLabelFromRecordUsing(fn (InstrumentAsset $instrument): string => trim($instrument->name.' — '.$instrument->status->label()))->preload()->searchable()->live()->afterStateUpdated(function (?int $state, Set $set): void {
                    $instrument = InstrumentAsset::query()->with('category')->find($state);
                    if ($instrument?->available_for_rental && $instrument->rental_pricing_mode !== 'override') {
                        app(InstrumentRentalPricing::class)->applyToInstrument($instrument);
                        if ($instrument->isDirty('instrument_category_id')) {
                            $instrument->save();
                        }
                        $instrument->load('category');
                    }
                    $source = self::defaultPricingSource($instrument);
                    $set('rental_pricing_source', $source);
                    $amount = self::amountForPricingSource($instrument, $source);
                    if ($amount !== null) {
                        $set('unit_amount', $amount);
                    }
                    $set('insurance_plan_id', null);
                    $set('insurance_monthly_amount', 0);
                })->required()->columnSpan(6),
                Select::make('person_id')->label('Client')->relationship('person', 'display_name')->searchable()->required()->columnSpan(6),
                DatePicker::make('starts_on')->label('Début prévu')->native(false)->columnSpan(3),
                DatePicker::make('expected_return_on')->label('Retour prévu')->native(false)->columnSpan(3),
                Select::make('rental_pricing_source')->label('Tarif appliqué')->options(fn (Get $get, ?Rental $record): array => self::pricingSourceOptions(InstrumentAsset::query()->with('category')->find($get('instrument_asset_id')), $record))->default('grid')->required()->live()->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                    $instrument = InstrumentAsset::query()->with('category')->find($get('instrument_asset_id'));
                    if ($state === 'grid' && $instrument?->available_for_rental) {
                        app(InstrumentRentalPricing::class)->applyToInstrument($instrument);
                        if ($instrument->isDirty('instrument_category_id')) {
                            $instrument->save();
                        }
                        $instrument->load('category');
                    }
                    $amount = self::amountForPricingSource($instrument, $state);
                    if ($amount !== null) {
                        $set('unit_amount', $amount);
                    }
                })->helperText(fn (Get $get): string => self::pricingSourceHelp((string) $get('rental_pricing_source')))->columnSpan(3),
                TextInput::make('unit_amount')->label(fn (Get $get): string => self::pricingAmountLabel((string) $get('rental_pricing_source')))->numeric()->prefix('€')->default(0)->disabled(fn (Get $get): bool => in_array($get('rental_pricing_source'), ['grid', 'instrument', 'recorded'], true))->dehydrated()->helperText(fn (Get $get): string => self::pricingAmountHelp((string) $get('rental_pricing_source')))->columnSpan(3),
                TextInput::make('deposit_amount')->label('Dépôt de garantie')->numeric()->prefix('€')->default(0)->columnSpan(3),
                Select::make('insurance_plan_id')->label('Assurance facultative')->options(function (Get $get): array {
                    $instrument = InstrumentAsset::query()->find($get('instrument_asset_id'));
                    if ($instrument === null) {
                        return [];
                    }

                    return app(RentalInsurancePricing::class)->eligiblePlans($instrument)
                        ->mapWithKeys(fn ($plan): array => [$plan->id => $plan->name.' — '.number_format((float) $plan->monthly_amount, 2, ',', ' ').' €/mois'])
                        ->all();
                })->searchable()->live()->afterStateUpdated(function (?int $state, Set $set): void {
                    $plan = $state === null ? null : RentalInsurancePlan::query()->find($state);
                    $set('insurance_monthly_amount', $plan?->monthly_amount ?? 0);
                })->helperText('Laissez vide lorsque le client ne souhaite pas cette protection.')->columnSpan(6),
                TextInput::make('insurance_monthly_amount')->label('Montant mensuel de l’assurance')->numeric()->prefix('€')->default(0)->disabled()->dehydrated()->helperText('Ce montant est celui choisi pour cette location et reste inchangé dans le dossier.')->columnSpan(6),
                DatePicker::make('returned_on')->label('Restitué le')->native(false)->columnSpan(3),
                Textarea::make('return_notes')->label('Constat de restitution')->rows(3)->columnSpan(9),
                Textarea::make('notes')->label(__('common.internal_notes'))->rows(4)->columnSpanFull(),
            ])->columns(12)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('updated_at', 'desc')->columns([
            TextColumn::make('reference')->label(__('common.reference'))->searchable(),
            TextColumn::make('instrument.name')->label('Instrument')->searchable(),
            TextColumn::make('person.display_name')->label('Client')->placeholder('—'),
            TextColumn::make('status')->label(__('common.status'))->badge(),
            TextColumn::make('latestAcceptance.status')->label('Accord du client')->state(fn (Rental $record): ?string => self::acceptanceLabel($record->latestAcceptance))->badge()->color(fn (Rental $record): string => self::acceptanceColor($record->latestAcceptance))->description(fn (Rental $record): ?string => self::acceptanceDescription($record->latestAcceptance))->placeholder('Pas encore demandé'),
            TextColumn::make('expected_return_on')->label('Retour prévu')->date('d/m/Y')->placeholder('—'),
            TextColumn::make('unit_amount')->label('Loyer mensuel')->money('EUR'),
            TextColumn::make('insurance_monthly_amount')->label('Assurance')->money('EUR')->placeholder('—')->toggleable(),
        ])->filters([SelectFilter::make('status')->label(__('common.status'))->options(RentalStatus::class)])->recordActions([EditAction::make()])->headerActions([CreateAction::make()->label('Nouvelle location')]);
    }

    public static function getPages(): array
    {
        return ['index' => ListRentals::route('/'), 'create' => CreateRental::route('/create'), 'edit' => EditRental::route('/{record}/edit')];
    }

    public static function acceptanceLabel(?RentalAcceptanceRequest $request): ?string
    {
        if ($request === null) {
            return null;
        }
        if (in_array($request->status, [RentalAcceptanceStatus::Created, RentalAcceptanceStatus::Sent], true) && $request->expires_at->isPast()) {
            return 'Expirée';
        }

        return $request->status->getLabel();
    }

    public static function acceptanceColor(?RentalAcceptanceRequest $request): string
    {
        return match (self::acceptanceLabel($request)) {
            'Acceptée' => 'success',
            'Envoyée' => 'warning',
            'Expirée', 'Refusée', 'Annulée' => 'danger',
            default => 'gray',
        };
    }

    public static function acceptanceDescription(?RentalAcceptanceRequest $request): ?string
    {
        if ($request === null) {
            return null;
        }

        return match (self::acceptanceLabel($request)) {
            'Acceptée' => 'Le '.$request->accepted_at?->format('d/m/Y'),
            'Envoyée' => 'Expire le '.$request->expires_at->format('d/m/Y'),
            'Expirée' => 'Expirée le '.$request->expires_at->format('d/m/Y'),
            default => null,
        };
    }

    public static function acceptanceTiming(?RentalAcceptanceRequest $request): string
    {
        if ($request === null) {
            return 'Générez les contrats, puis demandez l’accord du client lorsque le dossier est prêt.';
        }

        return match (self::acceptanceLabel($request)) {
            'Acceptée' => 'Acceptée le '.$request->accepted_at?->format('d/m/Y à H:i'),
            'Envoyée' => 'Envoyée le '.$request->sent_at?->format('d/m/Y à H:i').', valable jusqu’au '.$request->expires_at->format('d/m/Y'),
            'Expirée' => 'Le lien a expiré le '.$request->expires_at->format('d/m/Y').'. Envoyez une nouvelle demande si nécessaire.',
            'Annulée' => 'Demande annulée le '.$request->cancelled_at?->format('d/m/Y à H:i'),
            default => 'Demande préparée, pas encore envoyée.',
        };
    }

    /** @return array<string, string> */
    public static function pricingSourceOptions(?InstrumentAsset $instrument, ?Rental $record): array
    {
        $options = [];
        if ($record?->rental_pricing_source === 'recorded') {
            $options['recorded'] = 'Montant déjà enregistré pour ce dossier';
        }
        if ($instrument?->rental_pricing_mode === 'override') {
            $options['instrument'] = 'Tarif propre à cet instrument';
        }
        $grid = self::gridFor($instrument);
        if ($grid !== null) {
            $options['grid'] = 'Tarif de la grille — '.$grid->name;
        }
        $options['custom'] = 'Montant personnalisé pour cette location';

        return $options;
    }

    public static function defaultPricingSource(?InstrumentAsset $instrument): string
    {
        if ($instrument?->rental_pricing_mode === 'override') {
            return 'instrument';
        }

        return self::gridFor($instrument) === null ? 'custom' : 'grid';
    }

    public static function amountForPricingSource(?InstrumentAsset $instrument, ?string $source): ?float
    {
        $grid = self::gridFor($instrument);

        return match ($source) {
            'grid' => $grid === null ? null : (float) $grid->rental_monthly_amount,
            'instrument' => $instrument === null ? null : (float) ($instrument->rental_amount_override ?? 0),
            default => null,
        };
    }

    public static function pricingSourceHelp(string $source): string
    {
        return match ($source) {
            'grid' => 'Le loyer est repris de la grille de cet instrument.',
            'instrument' => 'Cet instrument a un tarif propre, différent de la grille.',
            'recorded' => 'Ce montant existait déjà dans ce dossier. Choisissez la grille ou un montant personnalisé pour le modifier.',
            default => 'Choisissez cette option uniquement si vous convenez d’un montant particulier avec le client.',
        };
    }

    public static function pricingAmountLabel(string $source): string
    {
        return match ($source) {
            'grid' => 'Loyer mensuel de la grille',
            'instrument' => 'Loyer mensuel de cet instrument',
            'recorded' => 'Loyer mensuel enregistré',
            default => 'Loyer mensuel convenu',
        };
    }

    public static function pricingAmountHelp(string $source): string
    {
        return match ($source) {
            'grid', 'instrument' => 'Modifiez le tarif appliqué si vous souhaitez convenir d’un montant particulier pour cette location.',
            'recorded' => 'Ce montant reste conservé tant que vous ne choisissez pas un autre tarif appliqué.',
            default => 'Ce montant restera associé à cette location, même si le tarif de la grille change ensuite.',
        };
    }

    private static function gridFor(?InstrumentAsset $instrument): ?InstrumentCategory
    {
        if ($instrument === null) {
            return null;
        }

        return app(InstrumentRentalPricing::class)->resolvedCategoryForInstrument($instrument);
    }
}
