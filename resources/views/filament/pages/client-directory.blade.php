<x-filament-panels::page>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="inline-flex w-fit rounded-lg bg-gray-100 p-1 dark:bg-white/10" role="tablist" aria-label="Type de client">
            @foreach (['all' => 'Tous', 'person' => 'Particuliers', 'company' => 'Entreprises'] as $key => $label)
                <button type="button" wire:click="selectType('{{ $key }}')" role="tab" aria-selected="{{ $type === $key ? 'true' : 'false' }}"
                    @class([
                        'rounded-md px-3 py-1.5 text-sm font-medium transition',
                        'bg-white text-gray-950 shadow-sm dark:bg-white/15 dark:text-white' => $type === $key,
                        'text-gray-600 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white' => $type !== $key,
                    ])>
                    {{ $label }}
                </button>
            @endforeach
        </div>
        <div class="w-full sm:max-w-md">
            <label for="client-search" class="sr-only">Rechercher un client</label>
            <input id="client-search" wire:model.live.debounce.300ms="search" type="search" placeholder="Rechercher un client"
                class="fi-input block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm shadow-sm outline-none transition duration-75 placeholder:text-gray-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white" />
        </div>
    </div>

    @php($clients = $this->clients())

    <div class="mt-4 overflow-hidden rounded-lg border border-gray-200 dark:border-white/10">
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
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $client->client_type === 'person' ? 'Particulier' : 'Entreprise' }}</td>
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
</x-filament-panels::page>
