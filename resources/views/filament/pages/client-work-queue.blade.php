<x-filament-panels::page>
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
        <h2 class="text-base font-semibold text-gray-950 dark:text-white">Vos prochains suivis client</h2>
        <p class="mt-1 max-w-3xl text-sm text-gray-600 dark:text-gray-300">Retrouvez au même endroit les demandes reçues, les messages en attente de réponse et les actions à réaliser. Ouvrez un élément pour consulter son historique et le traiter.</p>
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

    <div class="mt-4 space-y-3">
        @forelse ($items as $item)
            <a href="{{ $this->itemUrl($item) }}" class="block rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-primary-300 hover:bg-primary-50/30 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:hover:border-primary-500 dark:hover:bg-white/10">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200">{{ $this->itemTypeLabel($item) }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $item->contact }}</span>
                        </div>
                        <h3 class="mt-2 break-words text-sm font-semibold text-gray-950 dark:text-white">{{ $this->itemTitle($item) }}</h3>
                        <p class="mt-1 line-clamp-2 text-sm text-gray-600 dark:text-gray-300">{{ $item->summary }}</p>
                    </div>
                    <p class="shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $this->itemDateLabel($item) }} {{ \Illuminate\Support\Carbon::parse($item->sort_at)->format('d/m/Y à H:i') }}</p>
                </div>
            </a>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 bg-white px-4 py-10 text-center text-sm text-gray-500 dark:border-white/15 dark:bg-white/5 dark:text-gray-400">Aucun suivi client ne demande votre attention pour le moment.</div>
        @endforelse
    </div>

    @if ($items->hasPages())
        <nav class="mt-6 flex items-center justify-between gap-4" aria-label="Pagination des suivis client">
            @if ($items->onFirstPage())
                <span class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-400 dark:border-white/10 dark:text-gray-500">Précédent</span>
            @else
                <a href="{{ $items->previousPageUrl() }}" class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/10">Précédent</a>
            @endif

            <span class="text-sm text-gray-600 dark:text-gray-300">Page {{ $items->currentPage() }} sur {{ $items->lastPage() }}</span>

            @if ($items->hasMorePages())
                <a href="{{ $items->nextPageUrl() }}" class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-primary-500">Suivant</a>
            @else
                <span class="rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-400 dark:bg-white/10 dark:text-gray-500">Suivant</span>
            @endif
        </nav>
    @endif
</x-filament-panels::page>
