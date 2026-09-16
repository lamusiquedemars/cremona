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

        <x-filament::button type="submit">Enregistrer</x-filament::button>
    </form>
</x-filament-panels::page>
