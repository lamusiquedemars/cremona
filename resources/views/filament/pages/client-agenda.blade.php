<x-filament-panels::page>
    @php($days = $this->days())
    @php($events = $this->events())

    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Votre semaine de travail</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Rendez-vous, appels, rappels, dossiers atelier et retours de location réunis sans modifier leurs fiches d’origine.</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" wire:click="previousWeek" class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/10">Précédente</button>
                <button type="button" wire:click="currentWeek" class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/10">Aujourd’hui</button>
                <button type="button" wire:click="nextWeek" class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/10">Suivante</button>
            </div>
        </div>
    </div>

    <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/5">
        <div class="grid min-w-[980px] grid-cols-7 divide-x divide-gray-200 dark:divide-white/10">
            @foreach ($days as $day)
                @php($dayEvents = $events->filter(fn (array $event): bool => $event['starts_at']->isSameDay($day)))
                <section @class(['min-h-[26rem] p-3', 'bg-primary-50/40 dark:bg-primary-500/5' => $day->isToday()])>
                    <header class="border-b border-gray-100 pb-3 dark:border-white/10">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $day->isoFormat('ddd') }}</p>
                        <p class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $day->format('d') }}</p>
                    </header>
                    <div class="mt-3 space-y-2">
                        @forelse ($dayEvents as $event)
                            <a href="{{ $event['url'] }}" @class([
                                'block rounded-lg border p-3 transition hover:brightness-95 dark:hover:brightness-125',
                                'border-blue-200 bg-blue-50 text-blue-950 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-100' => $event['type'] === 'appointment',
                                'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100' => $event['type'] === 'task',
                                'border-violet-200 bg-violet-50 text-violet-950 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-100' => $event['type'] === 'workshop',
                                'border-emerald-200 bg-emerald-50 text-emerald-950 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100' => $event['type'] === 'rental',
                            ])>
                                <div class="flex items-center justify-between gap-2 text-xs font-medium">
                                    <span>{{ $event['label'] }}</span>
                                    <span>{{ $event['starts_at']->format('H:i') }}</span>
                                </div>
                                <p class="mt-1 break-words text-sm font-semibold">{{ $event['title'] }}</p>
                                <p class="mt-1 break-words text-xs opacity-75">{{ $event['contact'] }}</p>
                            </a>
                        @empty
                            <p class="pt-2 text-sm text-gray-400 dark:text-gray-500">Rien de prévu.</p>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    </div>

    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-xs text-gray-600 dark:text-gray-300">
        <span><i class="mr-1 inline-block size-2 rounded-full bg-blue-500"></i>Rendez-vous et appels</span>
        <span><i class="mr-1 inline-block size-2 rounded-full bg-amber-500"></i>Rappels</span>
        <span><i class="mr-1 inline-block size-2 rounded-full bg-violet-500"></i>Atelier</span>
        <span><i class="mr-1 inline-block size-2 rounded-full bg-emerald-500"></i>Locations</span>
    </div>
</x-filament-panels::page>
