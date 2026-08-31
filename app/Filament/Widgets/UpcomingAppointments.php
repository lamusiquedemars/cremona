<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Enums\OrganizationPermission;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\Appointment;
use App\Tenancy\OrganizationContext;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingAppointments extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 40;

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
            ->heading('Prochains rendez-vous')
            ->description('Les rendez-vous planifiés à partir de maintenant.')
            ->query(
                Appointment::query()
                    ->where('status', AppointmentStatus::Scheduled)
                    ->where('starts_at', '>=', now())
                    ->orderBy('starts_at')
                    ->limit(8),
            )
            ->columns([
                TextColumn::make('starts_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->timezone($timezone),
                TextColumn::make('title')
                    ->label('Rendez-vous')
                    ->weight('medium')
                    ->wrap(),
                TextColumn::make('person.display_name')
                    ->label('Contact')
                    ->placeholder('—'),
                TextColumn::make('modality')
                    ->label('Modalité')
                    ->badge(),
                TextColumn::make('assignedUser.name')
                    ->label('Responsable')
                    ->placeholder('Non attribué'),
            ])
            ->recordUrl(fn (Appointment $record): string => AppointmentResource::getUrl('view', ['record' => $record]))
            ->headerActions([
                Action::make('seeAll')
                    ->label('Voir tous les rendez-vous')
                    ->icon(Heroicon::OutlinedArrowRight)
                    ->url(AppointmentResource::getUrl('index')),
            ])
            ->paginated(false);
    }
}
