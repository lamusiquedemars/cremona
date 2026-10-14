<x-filament-panels::page>
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
        <h2 class="text-base font-semibold text-gray-950 dark:text-white">Ce qui demande votre attention</h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Demandes, messages auxquels répondre et tâches ouvertes. Chaque élément conserve son propre suivi.</p>
    </div>

    <div class="mt-4 inline-flex w-fit rounded-lg bg-gray-100 p-1 dark:bg-white/10" role="tablist" aria-label="Type d’élément à traiter">
        @foreach (['all' => 'Tout', 'request' => 'Demandes', 'conversation' => 'Messages', 'task' => 'Tâches'] as $key => $label)
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

    @php($items = $this->items())

    <div class="mt-4 overflow-hidden rounded-lg border border-gray-200 dark:border-white/10">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3 font-medium">À traiter</th>
                    <th class="hidden px-4 py-3 font-medium sm:table-cell">Client</th>
                    <th class="hidden px-4 py-3 font-medium md:table-cell">Type</th>
                    <th class="hidden px-4 py-3 font-medium lg:table-cell">Dernière action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white dark:divide-white/10 dark:bg-transparent">
                @forelse ($items as $item)
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-white/5">
                        <td class="px-4 py-3">
                            <a href="{{ $this->itemUrl($item) }}" class="font-medium text-primary-700 focus:outline-none focus:underline dark:text-primary-400">{{ $item->title }}</a>
                            <p class="mt-0.5 line-clamp-1 text-xs text-gray-600 dark:text-gray-300">{{ $item->summary }}</p>
                            <p class="mt-1 text-xs text-gray-500 sm:hidden dark:text-gray-400">{{ $item->contact }} · {{ $this->itemTypeLabel($item) }}</p>
                        </td>
                        <td class="hidden px-4 py-3 text-gray-600 sm:table-cell dark:text-gray-300">{{ $item->contact }}</td>
                        <td class="hidden px-4 py-3 md:table-cell"><span class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-700 dark:bg-white/10 dark:text-gray-200">{{ $this->itemTypeLabel($item) }}</span></td>
                        <td class="hidden px-4 py-3 text-gray-600 lg:table-cell dark:text-gray-300">{{ \Illuminate\Support\Carbon::parse($item->sort_at)->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Rien à traiter pour le moment.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($items->hasPages())
        <div class="mt-4">{{ $items->links() }}</div>
    @endif
</x-filament-panels::page>
