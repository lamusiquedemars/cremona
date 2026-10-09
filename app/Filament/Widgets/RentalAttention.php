<?php

namespace App\Filament\Widgets;

use App\Enums\OrganizationPermission;
use App\Enums\RentalAcceptanceStatus;
use App\Enums\RentalStatus;
use App\Filament\Resources\Rentals\RentalResource;
use App\Models\Rental;
use App\Services\OrganizationModuleAccess;
use App\Tenancy\OrganizationContext;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RentalAttention extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 45;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $organization = app(OrganizationContext::class)->current();
        $user = auth()->user();

        return $organization !== null
            && $user !== null
            && app(OrganizationModuleAccess::class)->enabled('rentals', $organization)
            && $user->hasOrganizationPermission(OrganizationPermission::ViewCrm, $organization);
    }

    public function table(Table $table): Table
    {
        $horizon = today()->addDays(14)->toDateString();

        return $table
            ->heading('Locations à suivre')
            ->description('Les locations qui demandent une action : préparation, accord du client ou retour à organiser.')
            ->query($this->attentionQuery($horizon))
            ->columns([
                TextColumn::make('reference')->label('Référence')->weight('medium'),
                TextColumn::make('instrument.name')->label('Instrument')->wrap(),
                TextColumn::make('person.display_name')->label('Client')->placeholder('—'),
                TextColumn::make('next_step')->label('Prochaine étape')->state(fn (Rental $record): string => $this->nextStep($record))->badge()->color(fn (Rental $record): string => $this->nextStepColor($record)),
                TextColumn::make('expected_return_on')->label('Retour prévu')->date('d/m/Y')->placeholder('—')->color(fn (Rental $record): string => $record->status === RentalStatus::Active && $record->expected_return_on?->isPast() ? 'danger' : 'gray'),
            ])
            ->recordUrl(fn (Rental $record): string => RentalResource::getUrl('edit', ['record' => $record]))
            ->headerActions([
                Action::make('seeAll')
                    ->label('Voir les locations')
                    ->icon(Heroicon::OutlinedArrowRight)
                    ->url(RentalResource::getUrl('index')),
            ])
            ->emptyStateHeading('Aucune location à suivre')
            ->emptyStateDescription('Les locations qui demandent une action apparaîtront ici.')
            ->paginated(false);
    }

    private function attentionQuery(string $horizon): Builder
    {
        return Rental::query()
            ->with(['instrument', 'person', 'latestAcceptance'])
            ->where(function (Builder $query) use ($horizon): void {
                $query->where('status', RentalStatus::Draft)
                    ->orWhere(fn (Builder $query): Builder => $query->where('status', RentalStatus::Active)->whereDate('expected_return_on', '<=', $horizon))
                    ->orWhereHas('latestAcceptance', fn (Builder $query): Builder => $query->whereIn('status', [RentalAcceptanceStatus::Created, RentalAcceptanceStatus::Sent]));
            })
            ->orderByRaw("case when status = 'draft' then 0 else 1 end")
            ->orderBy('expected_return_on')
            ->limit(8);
    }

    private function nextStep(Rental $rental): string
    {
        $acceptance = $rental->latestAcceptance;
        if ($acceptance !== null && in_array($acceptance->status, [RentalAcceptanceStatus::Created, RentalAcceptanceStatus::Sent], true)) {
            return $acceptance->expires_at->isPast() ? 'Renvoyer la demande d’accord' : 'Attendre l’accord du client';
        }
        if ($rental->status === RentalStatus::Draft && $acceptance?->status === RentalAcceptanceStatus::Accepted) {
            return 'Remettre l’instrument';
        }
        if ($rental->status === RentalStatus::Draft) {
            return 'Préparer la location';
        }
        if ($rental->expected_return_on?->isPast()) {
            return 'Relancer le retour';
        }

        return 'Préparer le retour';
    }

    private function nextStepColor(Rental $rental): string
    {
        if ($rental->status === RentalStatus::Active && $rental->expected_return_on?->isPast()) {
            return 'danger';
        }

        return $rental->latestAcceptance?->status === RentalAcceptanceStatus::Accepted ? 'success' : 'warning';
    }
}
