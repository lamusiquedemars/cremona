<?php

namespace App\Filament\Pages;

use App\Enums\OrganizationPermission;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\People\PersonResource;
use App\Models\Company;
use App\Models\Person;
use App\Models\User;
use App\Services\OrganizationModuleAccess;
use App\Tenancy\OrganizationContext;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;

class ClientDirectory extends Page
{
    use WithPagination;

    protected string $view = 'filament.pages.client-directory';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Suivi client';

    protected static ?string $navigationLabel = 'Clients';

    protected static ?int $navigationSort = 20;

    protected Width|string|null $maxContentWidth = Width::Full;

    public string $type = 'all';

    public string $search = '';

    public static function canAccess(): bool
    {
        $organization = app(OrganizationContext::class)->current();
        $user = auth()->user();

        return $organization !== null
            && $user instanceof User
            && $user->hasOrganizationPermission(OrganizationPermission::ViewCrm, $organization)
            && app(OrganizationModuleAccess::class)->enabled('crm');
    }

    public function updatedSearch(): void
    {
        $this->resetPage('clientsPage');
    }

    public function selectType(string $type): void
    {
        abort_unless(in_array($type, ['all', 'person', 'company'], true), 404);

        $this->type = $type;
        $this->resetPage('clientsPage');
    }

    /** @return LengthAwarePaginator<object> */
    public function clients(): LengthAwarePaginator
    {
        $search = trim($this->search);
        $people = $this->peopleQuery($search);
        $companies = $this->companiesQuery($search);

        $query = match ($this->type) {
            'person' => $people,
            'company' => $companies,
            default => $people->unionAll($companies),
        };

        return DB::query()
            ->fromSub($query, 'clients')
            ->orderBy('name')
            ->paginate(25, ['*'], 'clientsPage');
    }

    public function clientUrl(object $client): string
    {
        $organization = app(OrganizationContext::class)->require();

        return $client->client_type === 'company'
            ? CompanyResource::getUrl('view', ['record' => $client->id], tenant: $organization)
            : PersonResource::getUrl('view', ['record' => $client->id], tenant: $organization);
    }

    protected function getHeaderActions(): array
    {
        $organization = app(OrganizationContext::class)->current();
        $canCreate = $organization !== null && auth()->user()?->can('create', Person::class);

        return [
            Action::make('createPerson')
                ->label('Nouveau particulier')
                ->icon(Heroicon::OutlinedUserPlus)
                ->url(fn (): string => PersonResource::getUrl('create', tenant: $organization))
                ->visible($canCreate),
            Action::make('createCompany')
                ->label('Nouveau professionnel')
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->color('gray')
                ->url(fn (): string => CompanyResource::getUrl('create', tenant: $organization))
                ->visible($canCreate),
        ];
    }

    private function peopleQuery(string $search): Builder
    {
        return Person::query()
            ->select(['id', 'display_name as name', 'city', 'updated_at'])
            ->selectRaw("'person' as client_type")
            ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                $query->where('display_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            }));
    }

    private function companiesQuery(string $search): Builder
    {
        return Company::query()
            ->select(['id', 'name', 'city', 'updated_at'])
            ->selectRaw("'company' as client_type")
            ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('legal_name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            }));
    }
}
