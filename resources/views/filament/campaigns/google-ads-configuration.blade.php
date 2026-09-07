@php
    $state = $getState();
    $remote = $state['remote'] ?? null;
    $localGroups = collect(data_get($state, 'local.ad_groups', []))->keyBy(fn (array $group): string => mb_strtolower(trim((string) ($group['name'] ?? ''))));
    $normalise = fn (array $keywords): array => collect($keywords)
        ->map(fn (string $keyword): string => str_replace(['"', '“', '”'], '"', mb_strtolower(trim($keyword))))
        ->sort()
        ->values()
        ->all();
@endphp

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    @if (! is_array($remote))
        <p class="text-sm text-gray-600 dark:text-gray-300">Aucune configuration Google Ads n’est encore enregistrée. Ouvre ou actualise la campagne pour la relever.</p>
    @else
        <div class="grid gap-4">
            <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-sm text-gray-600 dark:text-gray-300">
                <span><strong class="text-gray-900 dark:text-gray-100">Google Ads</strong> · {{ data_get($remote, 'campaign.status') ?: 'État non fourni' }}</span>
                @if (filled($state['synced_at'] ?? null))
                    <span>Configuration relevée le {{ $state['synced_at'] }}</span>
                @endif
                @if (filled(data_get($remote, 'campaign.daily_budget')))
                    <span>Budget quotidien : {{ number_format((float) data_get($remote, 'campaign.daily_budget'), 2, ',', ' ') }}</span>
                @endif
            </div>

            @forelse (data_get($remote, 'ad_groups', []) as $group)
                @php
                    $local = $localGroups->get(mb_strtolower(trim((string) ($group['name'] ?? ''))));
                    $sameKeywords = $local !== null && $normalise((array) ($local['keywords'] ?? [])) === $normalise((array) ($group['keywords'] ?? []));
                    $sameNegatives = $local !== null && $normalise((array) ($local['negative_keywords'] ?? [])) === $normalise((array) ($group['negative_keywords'] ?? []));
                    $same = $sameKeywords && $sameNegatives;
                @endphp
                <article class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h4 class="font-semibold text-gray-950 dark:text-gray-50">{{ $group['name'] }}</h4>
                        @if ($same)
                            <span class="text-sm font-medium text-emerald-700 dark:text-emerald-300">Identique à la préparation Cremona</span>
                        @elseif ($local === null)
                            <span class="text-sm font-medium text-amber-700 dark:text-amber-300">Groupe présent dans Google seulement</span>
                        @else
                            <span class="text-sm font-medium text-amber-700 dark:text-amber-300">Différent de la préparation Cremona</span>
                        @endif
                    </div>

                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                        <div>
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Mots-clés dans Google</div>
                            <p class="mt-1 whitespace-pre-wrap text-sm text-gray-800 dark:text-gray-200">{{ collect($group['keywords'] ?? [])->implode("\n") ?: 'Aucun' }}</p>
                        </div>
                        <div>
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Exclusions dans Google</div>
                            <p class="mt-1 whitespace-pre-wrap text-sm text-gray-800 dark:text-gray-200">{{ collect($group['negative_keywords'] ?? [])->implode("\n") ?: 'Aucune' }}</p>
                        </div>
                    </div>
                </article>
            @empty
                <p class="text-sm text-gray-600 dark:text-gray-300">Google Ads n’a renvoyé aucun groupe d’annonces pour cette campagne.</p>
            @endforelse
        </div>
    @endif
</x-dynamic-component>
