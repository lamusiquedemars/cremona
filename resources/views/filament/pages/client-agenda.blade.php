<x-filament-panels::page>
    @php($days = $this->days())
    @php($events = $this->events())

    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ $agendaView === 'month' ? 'La vue d’ensemble de votre mois' : 'Votre semaine de travail' }}</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Rendez-vous, appels, rappels, dossiers atelier et retours de location réunis sans modifier leurs fiches d’origine.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="inline-flex rounded-lg bg-gray-100 p-1 dark:bg-white/10" role="tablist" aria-label="Vue de l’agenda">
                    @foreach (['week' => 'Semaine', 'month' => 'Mois'] as $value => $label)
                        <button type="button" wire:click="selectView('{{ $value }}')" @class([
                            'rounded-md px-3 py-1.5 text-sm font-medium transition',
                            'bg-white text-gray-950 shadow-sm dark:bg-white/15 dark:text-white' => $agendaView === $value,
                            'text-gray-600 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white' => $agendaView !== $value,
                        ])>{{ $label }}</button>
                    @endforeach
                </div>
                <button type="button" wire:click="previousPeriod" class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/10">Précédent</button>
                <button type="button" wire:click="currentPeriod" class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/10">Aujourd’hui</button>
                <button type="button" wire:click="nextPeriod" class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/10">Suivant</button>
            </div>
        </div>
    </div>

    <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/5">
        <div class="grid min-w-[980px] grid-cols-7 border-b border-gray-200 text-xs font-medium uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
            @foreach ($days->take(7) as $day)
                <div class="px-3 py-2">{{ $day->isoFormat('ddd') }}</div>
            @endforeach
        </div>
        <div @class([
            'grid min-w-[980px] grid-cols-7 divide-x divide-gray-200 dark:divide-white/10',
            'divide-y' => $agendaView === 'month',
        ])>
            @foreach ($days as $day)
                @php($dayEvents = $events->filter(fn (array $event): bool => $event['starts_at']->isSameDay($day)))
                <section @class([
                    'p-3',
                    'min-h-[26rem]' => $agendaView === 'week',
                    'min-h-40' => $agendaView === 'month',
                    'bg-primary-50/40 dark:bg-primary-500/5' => $day->isToday(),
                    'bg-gray-50/60 dark:bg-white/[0.02]' => $agendaView === 'month' && ! $day->isSameMonth($week),
                ])>
                    <header @class(['border-b border-gray-100 dark:border-white/10', 'pb-3' => $agendaView === 'week', 'pb-2' => $agendaView === 'month'])>
                        <p @class([
                            'inline-flex size-8 items-center justify-center rounded-full text-lg font-semibold',
                            'bg-primary-500 text-primary-950 shadow-sm' => $day->isToday(),
                            'text-gray-950 dark:text-white' => ! $day->isToday(),
                            'opacity-45' => $agendaView === 'month' && ! $day->isSameMonth($week),
                        ])>{{ $day->format('d') }}</p>
                    </header>
                    <div @class(['mt-3 space-y-2' => $agendaView === 'week', 'mt-2 space-y-1.5' => $agendaView === 'month'])>
                        @foreach ($dayEvents->take($agendaView === 'month' ? 3 : null) as $event)
                            <a href="{{ $event['url'] }}" @class([
                                'block rounded-lg border transition hover:brightness-95 dark:hover:brightness-125',
                                'p-3' => $agendaView === 'week',
                                'px-2 py-1.5' => $agendaView === 'month',
                                'border-blue-200 bg-blue-50 text-blue-950 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-100' => $event['type'] === 'appointment',
                                'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100' => $event['type'] === 'task',
                                'border-violet-200 bg-violet-50 text-violet-950 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-100' => $event['type'] === 'workshop',
                                'border-emerald-200 bg-emerald-50 text-emerald-950 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100' => $event['type'] === 'rental',
                            ])>
                                <div class="flex items-center justify-between gap-2 text-xs font-medium">
                                    <span>{{ $event['label'] }}</span>
                                    <span>{{ $event['starts_at']->format('H:i') }}</span>
                                </div>
                                <p @class(['mt-1 break-words font-semibold', 'text-sm' => $agendaView === 'week', 'text-xs' => $agendaView === 'month'])>{{ $event['title'] }}</p>
                                @if ($agendaView === 'week')
                                    <p class="mt-1 break-words text-xs opacity-75">{{ $event['contact'] }}</p>
                                @endif
                            </a>
                        @endforeach
                        @if ($dayEvents->isEmpty() && $agendaView === 'week')
                            <p class="pt-2 text-sm text-gray-400 dark:text-gray-500">Rien de prévu.</p>
                        @endif
                        @if ($agendaView === 'month' && $dayEvents->count() > 3)
                            <button type="button" wire:click="showWeek('{{ $day->toDateString() }}')" class="w-full rounded px-1 py-1 text-left text-xs font-medium text-primary-700 hover:bg-primary-50 dark:text-primary-300 dark:hover:bg-primary-500/10">+ {{ $dayEvents->count() - 3 }} autre{{ $dayEvents->count() - 3 > 1 ? 's' : '' }}</button>
                        @endif
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
