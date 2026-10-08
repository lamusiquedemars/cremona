<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Répertoire clients</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Particuliers et professionnels dans une même recherche, avec leurs fiches adaptées.</p>
            </div>
            <div class="w-full sm:max-w-md">
                <label for="client-search" class="sr-only">Rechercher un client</label>
                <input id="client-search" wire:model.live.debounce.300ms="search" type="search" placeholder="Rechercher un nom, une entreprise ou une ville"
                    class="fi-input block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm shadow-sm outline-none transition duration-75 placeholder:text-gray-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white" />
            </div>
        </div>

        <div class="mt-5 flex flex-wrap gap-2 border-b border-gray-200 pb-4 dark:border-white/10">
            @foreach (['all' => 'Tous', 'person' => 'Particuliers', 'company' => 'Professionnels'] as $key => $label)
                <button type="button" wire:click="selectType('{{ $key }}')"
                    @class([
                        'rounded-lg px-3 py-2 text-sm font-medium transition',
                        'bg-primary-600 text-white' => $type === $key,
                        'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:hover:bg-white/15' => $type !== $key,
                    ])>
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @php($clients = $this->clients())

        <div class="mt-2 overflow-hidden rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">Client</th>
                        <th class="px-4 py-3 font-medium">Type</th>
                        <th class="hidden px-4 py-3 font-medium sm:table-cell">Ville</th>
                        <th class="hidden px-4 py-3 font-medium md:table-cell">Mis à jour</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white dark:divide-white/10 dark:bg-transparent">
                    @forelse ($clients as $client)
                        <tr class="transition hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="px-4 py-3 font-medium text-primary-700 dark:text-primary-400">
                                <a href="{{ $this->clientUrl($client) }}" class="focus:outline-none focus:underline">{{ $client->name }}</a>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $client->client_type === 'person' ? 'Particulier' : 'Professionnel' }}</td>
                            <td class="hidden px-4 py-3 text-gray-600 dark:text-gray-300 sm:table-cell">{{ $client->city ?: '—' }}</td>
                            <td class="hidden px-4 py-3 text-gray-600 dark:text-gray-300 md:table-cell">{{ \Illuminate\Support\Carbon::parse($client->updated_at)->format('d/m/Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Aucun client ne correspond à cette recherche.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($clients->hasPages())
            <div class="mt-4">{{ $clients->links() }}</div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
