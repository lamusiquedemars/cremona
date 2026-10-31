<x-filament-panels::page>
    <form wire:submit="save" class="organization-details-form space-y-6">
        @if ($errors->any())
            <x-filament::section compact>
                <div class="rounded-lg border border-danger-300 bg-danger-50 px-4 py-3 text-sm text-danger-800 dark:border-danger-500/40 dark:bg-danger-500/10 dark:text-danger-200">
                    <p class="font-medium">Certaines informations ne peuvent pas être enregistrées :</p>
                    <ul class="mt-1 list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </x-filament::section>
        @endif
        <x-filament::section>
            <x-slot name="heading">Coordonnées et mentions légales</x-slot>
            <x-slot name="description">Ces informations décrivent l’organisation. Elles sont réutilisées par les documents et ne dépendent pas du module Devis.</x-slot>
            <div class="grid grid-cols-1 gap-x-5 gap-y-4 lg:grid-cols-6">
                @foreach (['display_name' => ['Nom affiché', 'lg:col-span-3'], 'legal_name' => ['Raison sociale ou nom complet', 'lg:col-span-3'], 'contact_name' => ['Personne à contacter', 'lg:col-span-2'], 'email' => ['Adresse email', 'lg:col-span-2'], 'phone' => ['Téléphone', 'lg:col-span-2'], 'website' => ['Site internet', 'lg:col-span-3'], 'registration_number' => ['SIRET ou numéro d’immatriculation', 'lg:col-span-3'], 'vat_number' => ['Numéro de TVA', 'lg:col-span-3'], 'address_line_1' => ['Adresse', 'lg:col-span-4'], 'address_line_2' => ['Complément d’adresse', 'lg:col-span-2'], 'postal_code' => ['Code postal', 'lg:col-span-1'], 'city' => ['Ville', 'lg:col-span-3'], 'country_code' => ['Pays (code ISO)', 'lg:col-span-2']] as $field => [$label, $span])
                    <label class="block {{ $span }}">
                        <span class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ $label }}</span>
                        <input wire:model="identity.{{ $field }}" type="{{ $field === 'email' ? 'email' : ($field === 'website' ? 'url' : 'text') }}" class="mt-1.5 block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/15 dark:bg-white/5 dark:text-white" @if ($field === 'country_code') maxlength="2" @endif>
                    </label>
                @endforeach
                <label class="block lg:col-span-6">
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-100">Mentions légales complémentaires</span>
                    <textarea wire:model="identity.legal_notice" rows="4" class="mt-1.5 block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/15 dark:bg-white/5 dark:text-white"></textarea>
                </label>
            </div>
        </x-filament::section>

        @if ($quotesEnabled)
            <x-filament::section>
                <x-slot name="heading">Réglages des devis</x-slot>
                <x-slot name="description">Ces valeurs ne concernent que le module Devis. Elles préremplissent les nouveaux devis, sans modifier les documents déjà émis.</x-slot>
                <div class="grid grid-cols-1 gap-x-5 gap-y-4 lg:grid-cols-6">
                    <label class="block lg:col-span-2">
                        <span class="text-sm font-medium text-gray-800 dark:text-gray-100">Validité par défaut (jours)</span>
                        <input wire:model="quoteRules.default_validity_days" type="number" min="1" max="365" class="mt-1.5 block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/15 dark:bg-white/5 dark:text-white">
                    </label>
                    <label class="block lg:col-span-4">
                        <span class="text-sm font-medium text-gray-800 dark:text-gray-100">Adresse des conditions générales</span>
                        <input wire:model="quoteRules.terms_url" type="url" class="mt-1.5 block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/15 dark:bg-white/5 dark:text-white">
                    </label>
                    <label class="block lg:col-span-3">
                        <span class="text-sm font-medium text-gray-800 dark:text-gray-100">Conditions de règlement par défaut</span>
                        <textarea wire:model="quoteRules.default_payment_terms" rows="4" class="mt-1.5 block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/15 dark:bg-white/5 dark:text-white"></textarea>
                    </label>
                    <label class="block lg:col-span-3">
                        <span class="text-sm font-medium text-gray-800 dark:text-gray-100">Note fiscale par défaut</span>
                        <textarea wire:model="quoteRules.default_tax_note" rows="4" class="mt-1.5 block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/15 dark:bg-white/5 dark:text-white"></textarea>
                    </label>
                </div>
            </x-filament::section>
        @endif

        @if ($invoicingEnabled)
            <x-filament::section>
                <x-slot name="heading">Réglages de facturation</x-slot>
                <x-slot name="description">Ces réglages préparent les nouvelles factures. Une facture émise conserve toujours ses propres instantanés et ne change donc jamais après coup.</x-slot>
                <div class="grid grid-cols-1 gap-x-5 gap-y-4 lg:grid-cols-6">
                    <label class="block lg:col-span-2"><span class="text-sm font-medium text-gray-800 dark:text-gray-100">Préfixe de numérotation</span><input wire:model="invoiceRules.reference_prefix" type="text" maxlength="24" class="mt-1.5 block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/15 dark:bg-white/5 dark:text-white"></label>
                    <label class="block lg:col-span-1"><span class="text-sm font-medium text-gray-800 dark:text-gray-100">Devise</span><input wire:model="invoiceRules.currency" type="text" maxlength="3" class="mt-1.5 block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/15 dark:bg-white/5 dark:text-white"></label>
                    <label class="block lg:col-span-1"><span class="text-sm font-medium text-gray-800 dark:text-gray-100">TVA par défaut</span><input wire:model="invoiceRules.default_tax_rate" type="number" min="0" max="100" step="0.01" class="mt-1.5 block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/15 dark:bg-white/5 dark:text-white"></label>
                    <label class="block lg:col-span-2"><span class="text-sm font-medium text-gray-800 dark:text-gray-100">Échéance par défaut (jours)</span><input wire:model="invoiceRules.default_due_days" type="number" min="0" max="365" class="mt-1.5 block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/15 dark:bg-white/5 dark:text-white"></label>
                    <label class="block lg:col-span-3"><span class="text-sm font-medium text-gray-800 dark:text-gray-100">Régime de TVA</span><select wire:model="invoiceRules.vat_regime" class="mt-1.5 block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm"><option value="standard">TVA applicable</option><option value="vat_exemption">Franchise en base</option></select></label>
                    <label class="block lg:col-span-3"><span class="text-sm font-medium text-gray-800 dark:text-gray-100">Factures de location</span><select wire:model="invoiceRules.rental_billing_mode" class="mt-1.5 block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm"><option value="manual">Proposer, puis valider manuellement</option><option value="disabled">Désactivées</option></select></label>
                    <label class="block lg:col-span-3"><span class="text-sm font-medium text-gray-800 dark:text-gray-100">Conditions de règlement</span><textarea wire:model="invoiceRules.default_payment_terms" rows="3" class="mt-1.5 block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/15 dark:bg-white/5 dark:text-white"></textarea></label>
                    <label class="block lg:col-span-3"><span class="text-sm font-medium text-gray-800 dark:text-gray-100">Instructions de paiement</span><textarea wire:model="invoiceRules.payment_instructions" rows="3" class="mt-1.5 block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/15 dark:bg-white/5 dark:text-white"></textarea></label>
                    <label class="block lg:col-span-6"><span class="text-sm font-medium text-gray-800 dark:text-gray-100">Retard de paiement</span><textarea wire:model="invoiceRules.late_payment_terms" rows="2" class="mt-1.5 block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/15 dark:bg-white/5 dark:text-white"></textarea></label>
                    <label class="flex items-start gap-3 lg:col-span-3"><input wire:model="invoiceRules.super_pdp_enabled" type="checkbox" class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500"><span><span class="block text-sm font-medium text-gray-800 dark:text-gray-100">Super PDP</span><span class="block text-xs text-gray-500">Canal réglementaire par défaut. L’activation technique exige ensuite le consentement OAuth de l’organisation.</span></span></label>
                    <label class="flex items-start gap-3 lg:col-span-3"><input wire:model="invoiceRules.pennylane_copy_enabled" type="checkbox" class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500"><span><span class="block text-sm font-medium text-gray-800 dark:text-gray-100">Copie comptable Pennylane</span><span class="block text-xs text-gray-500">Facultative : elle ne remplace jamais Super PDP.</span></span></label>
                    <label class="block lg:col-span-6"><span class="text-sm font-medium text-gray-800 dark:text-gray-100">Adresse Pennylane « factures de vente »</span><input wire:model="invoiceRules.pennylane_sales_email" type="email" class="mt-1.5 block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/15 dark:bg-white/5 dark:text-white"><span class="mt-1 block text-xs text-gray-500">Elle est chiffrée en base. Elle sera utilisée uniquement pour une copie comptable explicitement déclenchée.</span></label>
                </div>
            </x-filament::section>
        @endif

        <x-filament::section>
            <x-slot name="heading">Politique documentaire</x-slot>
            <x-slot name="description">Ces durées sont des valeurs de départ à valider par l’organisation selon son activité et ses obligations. Cremona n’efface rien automatiquement : une revue et une action explicite seront nécessaires à l’échéance.</x-slot>
            <div class="space-y-4">
                @foreach (\App\Support\PrivateDocumentPolicyCatalog::definitions() as $key => $definition)
                    <div class="grid grid-cols-1 items-end gap-x-5 gap-y-3 rounded-lg border border-gray-200 p-4 dark:border-white/10 lg:grid-cols-12">
                        <div class="lg:col-span-4"><p class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ $definition['label'] }}</p></div>
                        <label class="block lg:col-span-2"><span class="text-sm font-medium text-gray-800 dark:text-gray-100">Conservation (mois)</span><input wire:model="documentRules.{{ $key }}.retention_months" type="number" min="1" max="240" class="mt-1.5 block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/15 dark:bg-white/5 dark:text-white"></label>
                        <label class="block lg:col-span-3"><span class="text-sm font-medium text-gray-800 dark:text-gray-100">Accès</span><select wire:model="documentRules.{{ $key }}.access" class="mt-1.5 block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm"><option value="standard">Membres autorisés à consulter la relation client</option><option value="restricted">Administrateurs de l’organisation uniquement</option></select></label>
                        <label class="flex items-center gap-3 lg:col-span-3"><input wire:model="documentRules.{{ $key }}.requires_link" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"><span class="text-sm font-medium text-gray-800 dark:text-gray-100">Rattachement obligatoire</span></label>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Connexions et stockage</x-slot>
            <x-slot name="description">Chaque état concerne uniquement cette organisation. Les clés, jetons et secrets restent sur le serveur et ne sont jamais affichés ici.</x-slot>
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                    <div class="flex items-center justify-between gap-3"><p class="font-medium text-gray-900 dark:text-white">Documents privés — Scaleway</p><x-filament::badge :color="$integrationStates['storage_ready'] ? 'success' : 'warning'">{{ $integrationStates['storage_ready'] ? 'Actif' : 'À configurer' }}</x-filament::badge></div>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Les documents de {{ $integrationStates['organization_name'] }} restent privés ; leurs téléchargements sont contrôlés par Cremona.</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                    <div class="flex items-center justify-between gap-3"><p class="font-medium text-gray-900 dark:text-white">Super PDP</p><x-filament::badge :color="$integrationStates['super_pdp_ready'] ? 'success' : ($integrationStates['super_pdp_configured'] ? 'warning' : 'gray')">{{ $integrationStates['super_pdp_status'] ?: ($integrationStates['super_pdp_configured'] ? 'À vérifier' : 'Non configuré') }}</x-filament::badge></div>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $integrationStates['super_pdp_environment'] }} : {{ $integrationStates['super_pdp_company'] }}. @if ($integrationStates['super_pdp_checked_at']) Dernier contrôle : {{ \Illuminate\Support\Carbon::parse($integrationStates['super_pdp_checked_at'])->format('d/m/Y H:i') }}. @endif</p>
                    <div class="mt-3 space-y-3">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <label class="block"><span class="text-xs font-medium text-gray-700 dark:text-gray-200">Client ID de production</span><input wire:model="superPdpProductionCredentials.client_id" type="text" autocomplete="off" class="mt-1 block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm dark:border-white/15 dark:bg-white/5 dark:text-white"></label>
                            <label class="block"><span class="text-xs font-medium text-gray-700 dark:text-gray-200">Client secret de production</span><input wire:model="superPdpProductionCredentials.client_secret" type="password" autocomplete="new-password" class="mt-1 block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm dark:border-white/15 dark:bg-white/5 dark:text-white"></label>
                        </div>
                        <x-filament::button wire:click="connectSuperPdpProduction" type="button" size="sm">Enregistrer et vérifier la production</x-filament::button>
                        <p class="text-xs text-gray-500">Le secret est chiffré côté serveur et n’est jamais réaffiché. Cette vérification ne transmet aucune facture.</p>
                        @if ($integrationStates['super_pdp_configured'])
                            <x-filament::button wire:click="synchronizeIncomingSuperPdpInvoices" wire:loading.attr="disabled" wire:target="synchronizeIncomingSuperPdpInvoices" type="button" size="sm" color="gray">Synchroniser les factures reçues</x-filament::button>
                            <p class="text-xs text-gray-500">Télécharge les nouvelles factures reçues depuis Super PDP et conserve leur original dans les documents privés. Aucune facture n’est transmise.</p>
                            <x-filament::button wire:click="synchronizeElectronicReportingReceipts" wire:loading.attr="disabled" wire:target="synchronizeElectronicReportingReceipts" type="button" size="sm" color="gray">Actualiser les accusés e-reporting</x-filament::button>
                            <p class="text-xs text-gray-500">Relève les lots agrégés et leurs événements depuis Super PDP. Cette action est en lecture seule.</p>
                        @endif
                        @if ($integrationStates['super_pdp_sandbox_available'])
                            <x-filament::button wire:click="verifySuperPdpSandbox" type="button" size="sm" color="gray">Vérifier la sandbox</x-filament::button>
                        @endif
                    </div>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                    <p class="font-medium text-gray-900 dark:text-white">Google Ads</p><p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $integrationStates['google_ads_count'] }} compte configuré pour cette organisation.</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                    <p class="font-medium text-gray-900 dark:text-white">Brevo Meetings</p><p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $integrationStates['brevo_count'] }} connexion configurée pour cette organisation.</p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::button type="submit">Enregistrer</x-filament::button>
    </form>
</x-filament-panels::page>
