<x-filament-panels::page>
    @php($summary = $this->summary())

    <div class="grid gap-6 md:grid-cols-2">
        <x-filament::section>
            <x-slot name="heading">Credentials centraux Maracuja</x-slot>
            <x-slot name="description">Developer token et application OAuth utilisés par Cremona. Leurs valeurs ne sont jamais affichées.</x-slot>

            <x-filament::badge :color="$summary['central'] ? 'success' : 'warning'">
                {{ $summary['central'] ? 'Configuration centrale active' : 'Repli historique actif' }}
            </x-filament::badge>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Connexions organisationnelles</x-slot>
            <x-slot name="description">Les comptes clients et leurs autorisations restent strictement rattachés à leur organisation.</x-slot>

            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt>Comptes Google Ads reliés</dt>
                    <dd class="font-medium">{{ $summary['integrations'] }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt>Connexions avec credentials historiques conservés</dt>
                    <dd class="font-medium">{{ $summary['legacy'] }}</dd>
                </div>
            </dl>
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Séparation appliquée</x-slot>

        <p class="text-sm text-gray-600 dark:text-gray-300">
            Les administrateurs d’organisation voient uniquement leur identifiant de compte, l’état de connexion et la dernière synchronisation.
            Les secrets d’agence sont chargés côté serveur. Les anciens secrets chiffrés par organisation restent disponibles uniquement comme repli de compatibilité.
        </p>
    </x-filament::section>
</x-filament-panels::page>
