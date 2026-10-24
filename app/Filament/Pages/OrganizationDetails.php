<?php

namespace App\Filament\Pages;

use App\Enums\OrganizationPermission;
use App\Models\OrganizationLegalProfile;
use App\Models\OrganizationInvoiceSettings;
use App\Models\OrganizationQuoteSettings;
use App\Models\User;
use App\Services\OrganizationModuleAccess;
use App\Tenancy\OrganizationContext;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class OrganizationDetails extends Page
{
    protected string $view = 'filament.pages.organization-details';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration de l’organisation';

    protected static ?string $navigationLabel = 'Coordonnées et mentions légales';

    protected static ?string $title = 'Coordonnées et mentions légales';

    protected static ?int $navigationSort = 10;

    protected Width|string|null $maxContentWidth = Width::Full;

    /** @var array<string, mixed> */
    public array $identity = [];

    /** @var array<string, mixed> */
    public array $quoteRules = [];

    /** @var array<string, mixed> */
    public array $invoiceRules = [];

    public bool $quotesEnabled = false;

    public bool $invoicingEnabled = false;

    public ?string $superPdpAuthorizeUrl = null;

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
        $invoiceSettings = $organization->invoiceSettings;
        $this->quotesEnabled = app(OrganizationModuleAccess::class)->enabled('quotes');
        $this->invoicingEnabled = app(OrganizationModuleAccess::class)->enabled('invoicing');
        $this->superPdpAuthorizeUrl = $this->invoicingEnabled ? route('super-pdp.authorize', ['organization' => $organization]) : null;
        $this->identity = array_merge(['display_name' => $organization->name, 'country_code' => 'FR'], $profile?->only(['display_name', 'legal_name', 'contact_name', 'email', 'phone', 'website', 'address_line_1', 'address_line_2', 'postal_code', 'city', 'country_code', 'registration_number', 'vat_number', 'legal_notice']) ?? []);
        $this->quoteRules = $settings?->only(['default_validity_days', 'default_payment_terms', 'default_tax_note', 'terms_url']) ?? [];
        $this->invoiceRules = array_merge([
            'reference_prefix' => 'FAC', 'currency' => 'EUR', 'default_tax_rate' => 20,
            'vat_regime' => 'standard', 'regulatory_provider' => 'super_pdp',
            'super_pdp_enabled' => false, 'pennylane_copy_enabled' => false,
            'rental_billing_mode' => 'manual',
        ], $invoiceSettings?->only(['reference_prefix', 'currency', 'default_due_days', 'default_tax_rate', 'vat_regime', 'default_payment_terms', 'payment_instructions', 'late_payment_terms', 'regulatory_provider', 'super_pdp_enabled', 'pennylane_copy_enabled', 'pennylane_sales_email', 'rental_billing_mode']) ?? []);
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
            'invoiceRules.reference_prefix' => ['required', 'string', 'max:24'],
            'invoiceRules.currency' => ['required', 'string', 'size:3'],
            'invoiceRules.default_due_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'invoiceRules.default_tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'invoiceRules.vat_regime' => ['required', 'in:standard,vat_exemption'],
            'invoiceRules.default_payment_terms' => ['nullable', 'string'],
            'invoiceRules.payment_instructions' => ['nullable', 'string'],
            'invoiceRules.late_payment_terms' => ['nullable', 'string'],
            'invoiceRules.regulatory_provider' => ['required', 'in:super_pdp,none'],
            'invoiceRules.super_pdp_enabled' => ['boolean'],
            'invoiceRules.pennylane_copy_enabled' => ['boolean'],
            'invoiceRules.pennylane_sales_email' => ['nullable', 'email', 'max:255'],
            'invoiceRules.rental_billing_mode' => ['required', 'in:manual,disabled'],
        ]);
        $organization = app(OrganizationContext::class)->current();
        OrganizationLegalProfile::query()->updateOrCreate(['organization_id' => $organization->id], $data['identity']);
        if ($this->quotesEnabled) {
            OrganizationQuoteSettings::query()->updateOrCreate(['organization_id' => $organization->id], $data['quoteRules']);
        }
        if ($this->invoicingEnabled) {
            OrganizationInvoiceSettings::query()->updateOrCreate(['organization_id' => $organization->id], $data['invoiceRules']);
        }
        $organization->unsetRelation('legalProfile');
        $organization->unsetRelation('quoteSettings');
        $organization->unsetRelation('invoiceSettings');
        Notification::make()->title('Coordonnées et mentions légales enregistrées')->success()->send();
    }
}
