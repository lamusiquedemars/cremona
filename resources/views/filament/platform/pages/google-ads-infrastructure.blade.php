<x-filament-panels::page>
    @php($summary = $this->summary())

    <div class="grid gap-6 md:grid-cols-2">
        <x-filament::section>
            <x-slot name="heading">Infrastructure Maracuja</x-slot>
            <x-slot name="description">Un developer token, une application OAuth et une autorisation d’agence pour les comptes clients reliés au MCC.</x-slot>

            <x-filament::badge :color="$summary['mode'] ? 'success' : 'gray'">
                {{ $summary['mode'] ? 'Mode centralisé actif' : 'Mode historique conservé' }}
            </x-filament::badge>

            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt>Application OAuth Maracuja</dt><dd class="font-medium">{{ $summary['oauth'] ? 'Prête' : 'À configurer dans .env' }}</dd></div>
                <div class="flex justify-between gap-4"><dt>Accès API Google Ads</dt><dd class="font-medium">{{ $summary['api'] ? 'Basic ou Standard confirmé' : 'À confirmer dans l’API Center' }}</dd></div>
                <div class="flex justify-between gap-4"><dt>Autorisation agence</dt><dd class="font-medium">{{ $summary['authorization'] ? 'Chiffrée dans le coffre' : 'À autoriser' }}</dd></div>
            </dl>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Effet sur les organisations</x-slot>
            <x-slot name="description">Une organisation garde uniquement son identifiant de compte Google Ads. Les secrets ne sont jamais affichés dans son écran.</x-slot>

            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt>Comptes clients reliés</dt><dd class="font-medium">{{ $summary['integrations'] }}</dd></div>
                <div class="flex justify-between gap-4"><dt>Connexions historiques conservées</dt><dd class="font-medium">{{ $summary['legacy'] }}</dd></div>
            </dl>
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Bascule contrôlée</x-slot>
        <x-slot name="description">Aucune donnée d’organisation n’est modifiée par ces actions. Le retour au mode historique est immédiat.</x-slot>

        <div class="flex flex-wrap gap-3">
            @if (! $summary['authorization'])
                <x-filament::button tag="a" :href="route('google-ads.agency.authorize')" :disabled="! $summary['oauth']">
                    Autoriser le compte agence Google
                </x-filament::button>
            @endif

            @if (! $summary['mode'])
                <form method="POST" action="{{ route('google-ads.agency.enable') }}">
                    @csrf
                    <x-filament::button type="submit" color="success" :disabled="! $summary['ready']">
                        Activer l’infrastructure centralisée
                    </x-filament::button>
                </form>
            @else
                <form method="POST" action="{{ route('google-ads.agency.disable') }}">
                    @csrf
                    <x-filament::button type="submit" color="gray">
                        Revenir au mode historique
                    </x-filament::button>
                </form>
            @endif
        </div>

        <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
            Avant d’autoriser l’agence, ajoute l’URI de redirection
            <code>{{ route('google-ads.agency.callback') }}</code>
            au client OAuth Google Cloud. Cette autorisation ne crée ni ne diffuse de campagne.
        </p>
    </x-filament::section>
</x-filament-panels::page>
