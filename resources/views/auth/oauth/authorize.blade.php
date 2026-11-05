<x-filament-panels::page.simple>
    <x-filament::section>
        <x-slot name="heading">Ouvrir la gestion depuis {{ $client->name }}</x-slot>

        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ $client->name }} demande l’accès à votre espace de gestion Cremona.
            Vous pourrez revenir au site à tout moment.
        </p>

        <div class="mt-6 flex flex-wrap gap-3">
            <form method="post" action="{{ route('passport.authorizations.approve') }}">
                @csrf
                <input type="hidden" name="state" value="{{ $request->get('state') }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <x-filament::button type="submit">Continuer</x-filament::button>
            </form>

            <form method="post" action="{{ route('passport.authorizations.deny') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="{{ $request->get('state') }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <x-filament::button type="submit" color="gray">Annuler</x-filament::button>
            </form>
        </div>
    </x-filament::section>
</x-filament-panels::page.simple>
