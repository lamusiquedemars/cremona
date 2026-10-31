<?php

namespace App\Filament\Pages;

use App\Enums\OrganizationPermission;
use App\Models\OrganizationIntegration;
use App\Models\OrganizationInvoiceSettings;
use App\Models\OrganizationLegalProfile;
use App\Models\OrganizationQuoteSettings;
use App\Models\User;
use App\Services\OrganizationIntegrationManager;
use App\Services\IncomingSuperPdpInvoiceSynchronizer;
use App\Services\ElectronicReportingReceiptSynchronizer;
use App\Services\OrganizationModuleAccess;
use App\Services\SuperPdpClient;
use App\Services\SuperPdpSandboxClient;
use App\Support\PrivateDocumentPolicyCatalog;
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

    /** @var array<string, array{retention_months: int, access: string, requires_link: bool}> */
    public array $documentRules = [];

    /** @var array<string, mixed> */
    public array $integrationStates = [];

    /** @var array{client_id: string, client_secret: string} */
    public array $superPdpProductionCredentials = ['client_id' => '', 'client_secret' => ''];

    public bool $quotesEnabled = false;

    public bool $invoicingEnabled = false;

    public static function canAccess(): bool
    {
        $organization = app(OrganizationContext::class)->current();
        $user = auth()->user();

        return $organization !== null
            && $user instanceof User
            && $user->hasOrganizationPermission(OrganizationPermission::ManageOrganizationSettings, $organization);
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
        $this->documentRules = app(PrivateDocumentPolicyCatalog::class)->rules($organization);
        $this->refreshIntegrationStates();
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
        $this->normalizeSaveState();
        $this->normalizeUrls();
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
            'documentRules.*.retention_months' => ['required', 'integer', 'min:1', 'max:240'],
            'documentRules.*.access' => ['required', 'in:standard,restricted'],
            'documentRules.*.requires_link' => ['boolean'],
        ]);
        $organization = app(OrganizationContext::class)->current();
        OrganizationLegalProfile::query()->updateOrCreate(['organization_id' => $organization->id], $data['identity']);
        if ($this->quotesEnabled) {
            OrganizationQuoteSettings::query()->updateOrCreate(['organization_id' => $organization->id], $data['quoteRules']);
        }
        if ($this->invoicingEnabled) {
            OrganizationInvoiceSettings::query()->updateOrCreate(['organization_id' => $organization->id], $data['invoiceRules']);
        }
        $settings = $organization->settings ?? [];
        $settings['document_policy'] = ['categories' => $data['documentRules']];
        $organization->forceFill(['settings' => $settings])->save();
        $organization->unsetRelation('legalProfile');
        $organization->unsetRelation('quoteSettings');
        $organization->unsetRelation('invoiceSettings');
        Notification::make()->title('Coordonnées et mentions légales enregistrées')->success()->send();
    }

    public function verifySuperPdpSandbox(): void
    {
        $organization = app(OrganizationContext::class)->require();

        try {
            $state = app(SuperPdpSandboxClient::class)->checkConnection();
            app(OrganizationIntegrationManager::class)->configure(
                provider: 'super_pdp',
                name: 'sandbox',
                credentials: $state,
                actor: auth()->user(),
            );
            $this->refreshIntegrationStates();

            Notification::make()
                ->title('Connexion Super PDP vérifiée')
                ->body('Aucune facture n’a été créée ni transmise.')->success()->send();
        } catch (\Throwable $exception) {
            report($exception);
            $this->refreshIntegrationStates();
            Notification::make()
                ->title('Vérification Super PDP impossible')
                ->body('Vérifiez l’environnement sandbox et réessayez. Aucun document ni facture n’a été modifié.')
                ->danger()->persistent()->send();
        }
    }

    public function connectSuperPdpProduction(SuperPdpClient $client): void
    {
        $data = $this->validate([
            'superPdpProductionCredentials.client_id' => ['required', 'string', 'max:255'],
            'superPdpProductionCredentials.client_secret' => ['required', 'string', 'max:2048'],
        ]);

        try {
            $state = $client->checkConnection(
                $data['superPdpProductionCredentials']['client_id'],
                $data['superPdpProductionCredentials']['client_secret'],
            );
            app(OrganizationIntegrationManager::class)->configure(
                provider: 'super_pdp',
                name: 'production',
                credentials: array_merge($data['superPdpProductionCredentials'], $state),
                actor: auth()->user(),
            );
            $this->superPdpProductionCredentials = ['client_id' => '', 'client_secret' => ''];
            $this->refreshIntegrationStates();

            Notification::make()->title('Connexion Super PDP de production vérifiée')->success()->send();
        } catch (\Throwable $exception) {
            report($exception);
            $this->refreshIntegrationStates();
            Notification::make()
                ->title('Connexion Super PDP impossible')
                ->body('Vérifiez l’identifiant, le secret et la validation de l’entreprise dans Super PDP.')
                ->danger()->persistent()->send();
        }
    }

    public function synchronizeIncomingSuperPdpInvoices(IncomingSuperPdpInvoiceSynchronizer $synchronizer): void
    {
        try {
            $result = $synchronizer->synchronize(app(OrganizationContext::class)->require());
            $this->refreshIntegrationStates();

            Notification::make()
                ->title('Factures reçues Super PDP synchronisées')
                ->body($result['created'].' nouvelle(s), '.$result['updated'].' mise(s) à jour, '.$result['documents'].' original(aux) et '.$result['events'].' nouvel(aux) événement(s) conservé(s).')
                ->success()->send();
        } catch (\Throwable $exception) {
            report($exception);
            Notification::make()
                ->title('Synchronisation des factures reçues impossible')
                ->body($exception instanceof \LogicException ? $exception->getMessage() : 'Super PDP est temporairement inaccessible. Réessayez plus tard.')
                ->danger()->persistent()->send();
        }
    }

    public function synchronizeElectronicReportingReceipts(ElectronicReportingReceiptSynchronizer $synchronizer): void
    {
        try {
            $count = $synchronizer->synchronize(app(OrganizationContext::class)->require());
            Notification::make()->title('Accusés e-reporting Super PDP actualisés')->body($count.' lot(s) relevé(s).')->success()->send();
        } catch (\Throwable $exception) {
            report($exception);
            Notification::make()->title('Actualisation e-reporting impossible')
                ->body($exception instanceof \LogicException ? $exception->getMessage() : 'Super PDP est temporairement inaccessible. Réessayez plus tard.')
                ->danger()->persistent()->send();
        }
    }

    private function refreshIntegrationStates(): void
    {
        $organization = app(OrganizationContext::class)->require();
        $superPdpProduction = OrganizationIntegration::query()
            ->where('provider', 'super_pdp')->where('name', 'production')->first();
        $superPdpSandbox = OrganizationIntegration::query()
            ->where('provider', 'super_pdp')->where('name', 'sandbox')->first();
        $productionCredentials = $superPdpProduction?->credentials ?? [];
        $sandboxCredentials = $superPdpSandbox?->credentials ?? [];
        $storageReady = config('private-documents.disk') === 'scaleway-private'
            && filled(config('filesystems.disks.scaleway-private.bucket'))
            && filled(config('filesystems.disks.scaleway-private.key'))
            && filled(config('filesystems.disks.scaleway-private.secret'));

        $this->integrationStates = [
            'storage_ready' => $storageReady,
            'super_pdp_configured' => $superPdpProduction !== null,
            'super_pdp_environment' => $superPdpProduction !== null ? 'Production' : ($superPdpSandbox !== null ? 'Sandbox de test' : 'Production non connectée'),
            'super_pdp_company' => $superPdpProduction !== null
                ? $organization->name.(filled($productionCredentials['company_id'] ?? null) ? ' — compte #'.$productionCredentials['company_id'] : '')
                : ($superPdpSandbox !== null ? (string) config('services.super_pdp.sandbox_company_label', 'Burger Queen').' (test)' : '—'),
            'super_pdp_status' => $productionCredentials['verification_status'] ?? $sandboxCredentials['verification_status'] ?? null,
            'super_pdp_ready' => in_array($productionCredentials['verification_status'] ?? null, ['verified', 'active'], true),
            'super_pdp_checked_at' => $productionCredentials['checked_at'] ?? $sandboxCredentials['checked_at'] ?? null,
            'super_pdp_sandbox_available' => app(SuperPdpSandboxClient::class)->isConfigured(),
            'google_ads_count' => OrganizationIntegration::query()->where('provider', 'google_ads')->where('name', 'reporting')->count(),
            'brevo_count' => OrganizationIntegration::query()->where('provider', 'brevo')->where('name', 'meetings')->count(),
            'organization_name' => $organization->name,
        ];
    }

    private function normalizeUrls(): void
    {
        foreach ([['identity', 'website'], ['quoteRules', 'terms_url']] as [$section, $field]) {
            $value = trim((string) ($this->{$section}[$field] ?? ''));
            if ($value !== '' && ! preg_match('#^[a-z][a-z0-9+.-]*://#i', $value)) {
                $value = 'https://'.$value;
            }
            $this->{$section}[$field] = $value;
        }
        $country = trim((string) ($this->identity['country_code'] ?? ''));
        $this->identity['country_code'] = match (strtolower($country)) {
            '', 'france' => $country === '' ? null : 'FR',
            default => strtoupper($country),
        };
    }

    private function normalizeSaveState(): void
    {
        $this->invoiceRules = array_replace([
            'reference_prefix' => 'FAC', 'currency' => 'EUR', 'default_tax_rate' => 20,
            'vat_regime' => 'standard', 'regulatory_provider' => 'super_pdp',
            'super_pdp_enabled' => false, 'pennylane_copy_enabled' => false,
            'rental_billing_mode' => 'manual',
        ], $this->invoiceRules);
        foreach (['super_pdp_enabled', 'pennylane_copy_enabled'] as $field) {
            $this->invoiceRules[$field] = filter_var($this->invoiceRules[$field], FILTER_VALIDATE_BOOLEAN);
        }

        $defaults = app(PrivateDocumentPolicyCatalog::class)->rules(app(OrganizationContext::class)->require());
        foreach ($defaults as $category => $rule) {
            $this->documentRules[$category] = array_replace($rule, is_array($this->documentRules[$category] ?? null) ? $this->documentRules[$category] : []);
            $this->documentRules[$category]['requires_link'] = filter_var($this->documentRules[$category]['requires_link'], FILTER_VALIDATE_BOOLEAN);
        }
    }
}
