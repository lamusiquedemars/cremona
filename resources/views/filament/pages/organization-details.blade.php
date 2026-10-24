<x-filament-panels::page>
    <form wire:submit="save" class="organization-details-form space-y-6">
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
                    @if ($superPdpAuthorizeUrl)
                        <div class="lg:col-span-3"><a href="{{ $superPdpAuthorizeUrl }}" class="inline-flex items-center rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-500">Connecter Super PDP (sandbox)</a><p class="mt-1 text-xs text-gray-500">Burger Queen uniquement : cette connexion de test sera remplacée avant la mise en production.</p></div>
                    @endif
                    <label class="flex items-start gap-3 lg:col-span-3"><input wire:model="invoiceRules.pennylane_copy_enabled" type="checkbox" class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500"><span><span class="block text-sm font-medium text-gray-800 dark:text-gray-100">Copie comptable Pennylane</span><span class="block text-xs text-gray-500">Facultative : elle ne remplace jamais Super PDP.</span></span></label>
                    <label class="block lg:col-span-6"><span class="text-sm font-medium text-gray-800 dark:text-gray-100">Adresse Pennylane « factures de vente »</span><input wire:model="invoiceRules.pennylane_sales_email" type="email" class="mt-1.5 block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/15 dark:bg-white/5 dark:text-white"><span class="mt-1 block text-xs text-gray-500">Elle est chiffrée en base. Elle sera utilisée uniquement pour une copie comptable explicitement déclenchée.</span></label>
                </div>
            </x-filament::section>
        @endif

        <x-filament::button type="submit">Enregistrer</x-filament::button>
    </form>
</x-filament-panels::page>
