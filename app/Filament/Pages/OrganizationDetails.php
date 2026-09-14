<?php

namespace App\Filament\Pages;

use App\Enums\OrganizationPermission;
use App\Models\OrganizationLegalProfile;
use App\Models\OrganizationQuoteSettings;
use App\Models\User;
use App\Services\OrganizationModuleAccess;
use App\Tenancy\OrganizationContext;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rule;

class OrganizationDetails extends Page
{
    protected string $view = 'filament.pages.organization-details';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration de l’organisation';

    protected static ?string $navigationLabel = 'Coordonnées et mentions légales';

    protected static ?string $title = 'Coordonnées et mentions légales';

    protected static ?int $navigationSort = 10;

    /** @var array<string, mixed> */
    public array $identity = [];

    /** @var array<string, mixed> */
    public array $quoteRules = [];

    public bool $quotesEnabled = false;

    public static function canAccess(): bool
    {
        $organization = app(OrganizationContext::class)->current();
        $user = auth()->user();

        return $organization !== null
            && $user instanceof User
            && $user->hasOrganizationPermission(OrganizationPermission::ManageIntegrations, $organization);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $organization = app(OrganizationContext::class)->current();
        $profile = $organization->legalProfile;
        $settings = $organization->quoteSettings;
        $this->quotesEnabled = app(OrganizationModuleAccess::class)->enabled('quotes');
        $this->identity = array_merge(['display_name' => $organization->name, 'country_code' => 'FR'], $profile?->only(['display_name', 'legal_name', 'contact_name', 'email', 'phone', 'website', 'address_line_1', 'address_line_2', 'postal_code', 'city', 'country_code', 'registration_number', 'vat_number', 'legal_notice']) ?? []);
        $this->quoteRules = $settings?->only(['default_validity_days', 'default_payment_terms', 'default_tax_note', 'terms_url']) ?? [];
    }

    public function save(): void
    {
        $data = $this->validate([
            'identity.display_name' => ['nullable', 'string', 'max:255'],
            'identity.legal_name' => ['nullable', 'string', 'max:255'],
            'identity.contact_name' => ['nullable', 'string', 'max:255'],
            'identity.email' => ['nullable', 'email', 'max:255'],
            'identity.phone' => ['nullable', 'string', 'max:255'],
            'identity.website' => ['nullable', 'url', 'max:2048'],
            'identity.address_line_1' => ['nullable', 'string', 'max:255'],
            'identity.address_line_2' => ['nullable', 'string', 'max:255'],
            'identity.postal_code' => ['nullable', 'string', 'max:32'],
            'identity.city' => ['nullable', 'string', 'max:255'],
            'identity.country_code' => ['nullable', 'string', 'size:2'],
            'identity.registration_number' => ['nullable', 'string', 'max:255'],
            'identity.vat_number' => ['nullable', 'string', 'max:255'],
            'identity.legal_notice' => ['nullable', 'string'],
            'quoteRules.default_validity_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'quoteRules.default_payment_terms' => ['nullable', 'string'],
            'quoteRules.default_tax_note' => ['nullable', 'string'],
            'quoteRules.terms_url' => ['nullable', 'url', 'max:2048'],
        ]);
        $organization = app(OrganizationContext::class)->current();
        OrganizationLegalProfile::query()->updateOrCreate(['organization_id' => $organization->id], $data['identity']);
        if ($this->quotesEnabled) {
            OrganizationQuoteSettings::query()->updateOrCreate(['organization_id' => $organization->id], $data['quoteRules']);
        }
        $organization->unsetRelation('legalProfile');
        $organization->unsetRelation('quoteSettings');
        Notification::make()->title('Coordonnées et mentions légales enregistrées')->success()->send();
    }
}
