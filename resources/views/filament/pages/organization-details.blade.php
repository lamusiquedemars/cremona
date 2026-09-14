<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Coordonnées et mentions légales</x-slot>
            <x-slot name="description">Ces informations décrivent l’organisation. Elles sont réutilisées par les documents et ne dépendent pas du module Devis.</x-slot>
            <div class="grid gap-4 md:grid-cols-2">
                @foreach (['display_name' => 'Nom affiché', 'legal_name' => 'Raison sociale ou nom complet', 'contact_name' => 'Personne à contacter', 'email' => 'Adresse email', 'phone' => 'Téléphone', 'website' => 'Site internet', 'address_line_1' => 'Adresse', 'address_line_2' => 'Complément d’adresse', 'postal_code' => 'Code postal', 'city' => 'Ville', 'country_code' => 'Pays (code ISO)', 'registration_number' => 'SIRET ou numéro d’immatriculation', 'vat_number' => 'Numéro de TVA'] as $field => $label)
                    <label class="block text-sm font-medium text-gray-800 dark:text-gray-100">{{ $label }}<input wire:model="identity.{{ $field }}" type="text" class="mt-1 block w-full rounded-lg border-gray-300 bg-white shadow-sm dark:border-white/15 dark:bg-white/5"></label>
                @endforeach
                <label class="block text-sm font-medium text-gray-800 md:col-span-2 dark:text-gray-100">Mentions légales complémentaires<textarea wire:model="identity.legal_notice" rows="4" class="mt-1 block w-full rounded-lg border-gray-300 bg-white shadow-sm dark:border-white/15 dark:bg-white/5"></textarea></label>
            </div>
        </x-filament::section>

        @if ($quotesEnabled)
            <x-filament::section>
                <x-slot name="heading">Réglages des devis</x-slot>
                <x-slot name="description">Ces valeurs ne concernent que le module Devis. Elles préremplissent les nouveaux devis, sans modifier les documents déjà émis.</x-slot>
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block text-sm font-medium text-gray-800 dark:text-gray-100">Validité par défaut (jours)<input wire:model="quoteRules.default_validity_days" type="number" min="1" max="365" class="mt-1 block w-full rounded-lg border-gray-300 bg-white shadow-sm dark:border-white/15 dark:bg-white/5"></label>
                    <label class="block text-sm font-medium text-gray-800 dark:text-gray-100">Adresse des conditions générales<input wire:model="quoteRules.terms_url" type="url" class="mt-1 block w-full rounded-lg border-gray-300 bg-white shadow-sm dark:border-white/15 dark:bg-white/5"></label>
                    <label class="block text-sm font-medium text-gray-800 md:col-span-2 dark:text-gray-100">Conditions de règlement par défaut<textarea wire:model="quoteRules.default_payment_terms" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 bg-white shadow-sm dark:border-white/15 dark:bg-white/5"></textarea></label>
                    <label class="block text-sm font-medium text-gray-800 md:col-span-2 dark:text-gray-100">Note fiscale par défaut<textarea wire:model="quoteRules.default_tax_note" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 bg-white shadow-sm dark:border-white/15 dark:bg-white/5"></textarea></label>
                </div>
            </x-filament::section>
        @endif

        <x-filament::button type="submit">Enregistrer</x-filament::button>
    </form>
</x-filament-panels::page>
