<?php

namespace App\Filament\Pages;

use App\Enums\AppointmentStatus;
use App\Enums\CrmTaskStatus;
use App\Enums\OrganizationPermission;
use App\Enums\RentalStatus;
use App\Enums\WorkshopOrderStatus;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\CrmTasks\CrmTaskResource;
use App\Filament\Resources\Rentals\RentalResource;
use App\Filament\Resources\WorkshopOrders\WorkshopOrderResource;
use App\Models\Appointment;
use App\Models\CrmTask;
use App\Models\Rental;
use App\Models\User;
use App\Models\WorkshopOrder;
use App\Services\OrganizationModuleAccess;
use App\Services\OrganizationPresentation;
use App\Tenancy\OrganizationContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class ClientAgenda extends Page
{
    protected string $view = 'filament.pages.client-agenda';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Suivi client';

    protected static ?string $navigationLabel = 'Agenda';

    protected static ?int $navigationSort = 35;

    protected Width|string|null $maxContentWidth = Width::Full;

    public string $week;

    public string $agendaView = 'week';

    public function mount(): void
    {
        $this->week = $this->weekStart()->toDateString();
    }

    public static function canAccess(): bool
    {
        $organization = app(OrganizationContext::class)->current();
        $user = auth()->user();

        return $organization !== null
            && $user instanceof User
            && $user->hasOrganizationPermission(OrganizationPermission::ViewCrm, $organization)
            && app(OrganizationModuleAccess::class)->enabled('crm', $organization);
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return app(OrganizationPresentation::class)->navigationGroupLabel('customer_follow_up', 'Suivi client');
    }

    public function getTitle(): string
    {
        return 'Agenda';
    }

    public function selectView(string $view): void
    {
        abort_unless(in_array($view, ['week', 'month'], true), 404);

        $this->agendaView = $view;
    }

    public function previousPeriod(): void
    {
        $this->week = $this->agendaView === 'month'
            ? $this->anchorDate()->subMonthNoOverflow()->toDateString()
            : $this->weekStart()->subWeek()->toDateString();
    }

    public function nextPeriod(): void
    {
        $this->week = $this->agendaView === 'month'
            ? $this->anchorDate()->addMonthNoOverflow()->toDateString()
            : $this->weekStart()->addWeek()->toDateString();
    }

    public function currentPeriod(): void
    {
        $this->week = $this->today()->toDateString();
    }

    public function showWeek(string $date): void
    {
        $this->week = CarbonImmutable::parse($date, app(OrganizationContext::class)->require()->timezone())->toDateString();
        $this->agendaView = 'week';
    }

    /** @return Collection<int, CarbonImmutable> */
    public function days(): Collection
    {
        $start = $this->periodStart();
        $end = $this->periodEnd();

        return collect(range(0, $start->diffInDays($end)))->map(fn (int $offset): CarbonImmutable => $start->addDays($offset));
    }

    /** @return Collection<int, array{type: string, label: string, title: string, contact: string, starts_at: CarbonImmutable, url: string}> */
    public function events(): Collection
    {
        $start = $this->periodStart()->startOfDay();
        $end = $this->periodEnd()->addDay()->startOfDay();
        $organization = app(OrganizationContext::class)->require();
        $events = collect();

        if ($this->moduleEnabled('appointments', $organization)) {
            $events = $events->concat(Appointment::query()
                ->with(['person', 'company'])
                ->where('status', AppointmentStatus::Scheduled)
                ->where('starts_at', '<', $end->utc())
                ->where('ends_at', '>', $start->utc())
                ->get()
                ->map(fn (Appointment $appointment): array => [
                    'type' => 'appointment',
                    'label' => $appointment->modality->getLabel(),
                    'title' => $appointment->title,
                    'contact' => $appointment->person?->display_name ?? $appointment->company?->name ?? 'Contact à préciser',
                    'starts_at' => CarbonImmutable::instance($appointment->starts_at)->setTimezone($organization->timezone()),
                    'url' => AppointmentResource::getUrl('view', ['record' => $appointment]),
                ]));
        }

        $events = $events->concat(CrmTask::query()
            ->with(['person', 'company'])
            ->whereIn('status', [CrmTaskStatus::Open, CrmTaskStatus::InProgress])
            ->whereBetween('due_at', [$start->utc(), $end->utc()])
            ->get()
            ->map(fn (CrmTask $task): array => [
                'type' => 'task',
                'label' => 'Rappel',
                'title' => $task->title,
                'contact' => $task->person?->display_name ?? $task->company?->name ?? 'Sans client lié',
                'starts_at' => CarbonImmutable::instance($task->due_at)->setTimezone($organization->timezone()),
                'url' => CrmTaskResource::getUrl('view', ['record' => $task]),
            ]));

        if ($this->moduleEnabled('workshop', $organization)) {
            $events = $events->concat(WorkshopOrder::query()
                ->with('person')
                ->whereNotIn('status', [WorkshopOrderStatus::Returned, WorkshopOrderStatus::Cancelled])
                ->whereNotNull('scheduled_at')
                ->whereBetween('scheduled_at', [$start->utc(), $end->utc()])
                ->get()
                ->map(fn (WorkshopOrder $order): array => [
                    'type' => 'workshop',
                    'label' => 'Atelier',
                    'title' => $order->title,
                    'contact' => $order->person?->display_name ?? 'Client à préciser',
                    'starts_at' => CarbonImmutable::instance($order->scheduled_at)->setTimezone($organization->timezone()),
                    'url' => WorkshopOrderResource::getUrl('view', ['record' => $order]),
                ]));
        }

        if ($this->moduleEnabled('rentals', $organization)) {
            $events = $events->concat(Rental::query()
                ->with('person')
                ->whereNotIn('status', [RentalStatus::Returned, RentalStatus::Cancelled])
                ->whereBetween('expected_return_on', [$start->toDateString(), $end->subDay()->toDateString()])
                ->get()
                ->map(fn (Rental $rental): array => [
                    'type' => 'rental',
                    'label' => 'Retour de location',
                    'title' => $rental->reference,
                    'contact' => $rental->person?->display_name ?? 'Client à préciser',
                    'starts_at' => CarbonImmutable::parse($rental->expected_return_on, $organization->timezone())->startOfDay(),
                    'url' => RentalResource::getUrl('view', ['record' => $rental]),
                ]));
        }

        return $events->sortBy('starts_at')->values();
    }

    protected function getHeaderActions(): array
    {
        $organization = app(OrganizationContext::class)->current();

        return [
            Action::make('createAppointment')
                ->label('Nouveau rendez-vous')
                ->icon(Heroicon::OutlinedPlus)
                ->url(fn (): string => AppointmentResource::getUrl('create', tenant: $organization))
                ->visible(fn (): bool => $organization !== null && auth()->user()?->can('create', Appointment::class)),
        ];
    }

    private function weekStart(): CarbonImmutable
    {
        $date = filled($this->week ?? null)
            ? CarbonImmutable::parse($this->week, app(OrganizationContext::class)->require()->timezone())
            : $this->today();

        return $date->startOfWeek(CarbonInterface::MONDAY);
    }

    private function periodStart(): CarbonImmutable
    {
        if ($this->agendaView !== 'month') {
            return $this->weekStart();
        }

        return $this->anchorDate()->startOfMonth()->startOfWeek(CarbonInterface::MONDAY);
    }

    private function periodEnd(): CarbonImmutable
    {
        if ($this->agendaView !== 'month') {
            return $this->weekStart()->addDays(6);
        }

        return $this->anchorDate()->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY);
    }

    private function anchorDate(): CarbonImmutable
    {
        return filled($this->week ?? null)
            ? CarbonImmutable::parse($this->week, app(OrganizationContext::class)->require()->timezone())
            : $this->today();
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(app(OrganizationContext::class)->require()->timezone());
    }

    private function moduleEnabled(string $module, object $organization): bool
    {
        return app(OrganizationModuleAccess::class)->enabled($module, $organization);
    }
}
