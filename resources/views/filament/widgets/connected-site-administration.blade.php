<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-globe-alt">
        <x-slot name="heading">Administration du site</x-slot>
        <x-slot name="description">Modifiez le contenu, les réglages et les fonctionnalités du site lié à cette organisation.</x-slot>

        <x-filament::button tag="a" :href="$url" icon="heroicon-o-arrow-top-right-on-square">
            Ouvrir l’administration du site
        </x-filament::button>
    </x-filament::section>
</x-filament-widgets::widget>
