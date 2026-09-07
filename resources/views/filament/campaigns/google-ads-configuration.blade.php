@php
    $state = $getState(); $remote = $state['remote'] ?? null;
    $key = fn (array $g): string => mb_strtolower(trim((string) ($g['name'] ?? '')));
    $words = fn (array $v): array => collect($v)->filter('is_string')->mapWithKeys(fn (string $w) => [str_replace(['"', '“', '”'], '"', mb_strtolower(trim($w))) => trim($w)])->all();
    $local = collect(data_get($state, 'local.ad_groups', []))->filter(fn ($g) => is_array($g) && $key($g) !== '')->keyBy($key);
    $google = collect(data_get($remote, 'ad_groups', []))->filter(fn ($g) => is_array($g) && $key($g) !== '')->keyBy($key);
    $groups = $local->keys()->merge($google->keys())->unique()->sort()->map(function ($id) use ($local, $google, $words) {
        $l = $local->get($id); $g = $google->get($id); $changes = [];
        foreach (['keywords' => 'Mots-clés', 'negative_keywords' => 'Exclusions'] as $field => $label) {
            $a = $words((array) ($l[$field] ?? [])); $b = $words((array) ($g[$field] ?? []));
            $changes[$field] = ['label' => $label, 'added' => array_values(array_diff_key($b, $a)), 'missing' => array_values(array_diff_key($a, $b))];
        }
        $same = $l && $g && collect($changes)->every(fn ($c) => $c['added'] === [] && $c['missing'] === []);
        return ['name' => $g['name'] ?? $l['name'] ?? 'Groupe sans nom', 'local' => $l, 'google' => $g, 'same' => $same, 'changes' => $changes];
    })->values();
    $different = $groups->where('same', false); $identical = $groups->where('same', true);
@endphp

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    @if (! is_array($remote))
        <p class="text-sm text-gray-600 dark:text-gray-300">Aucune configuration Google Ads n’est encore enregistrée. Ouvre ou actualise la campagne pour la relever.</p>
    @else
        <div class="grid gap-4">
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
                <div><div class="font-semibold text-gray-950 dark:text-gray-50">{{ $different->isEmpty() ? 'Configuration identique à la préparation Cremona' : $different->count().' groupe'.($different->count() > 1 ? 's' : '').' à vérifier' }}</div><div class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">Google Ads est observé ; Cremona ne remplace rien automatiquement.</div></div>
                <div class="text-right text-sm text-gray-600 dark:text-gray-300">{{ data_get($remote, 'campaign.status') ?: 'État non fourni' }}@if(filled($state['synced_at'] ?? null))<br>Relevée le {{ $state['synced_at'] }}@endif</div>
            </div>

            @foreach ($different as $group)
                <article class="rounded-xl border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-900/70 dark:bg-amber-950/20">
                    <div class="flex flex-wrap items-center justify-between gap-2"><h4 class="font-semibold text-gray-950 dark:text-gray-50">{{ $group['name'] }}</h4><span class="text-sm font-medium text-amber-800 dark:text-amber-200">{{ $group['local'] === null ? 'Présent dans Google seulement' : ($group['google'] === null ? 'Présent dans Cremona seulement' : 'Écart détecté') }}</span></div>
                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        @foreach ($group['changes'] as $change)
                            <section class="rounded-lg border border-amber-200/80 bg-white/80 p-3 dark:border-amber-900/60 dark:bg-gray-900/60"><h5 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $change['label'] }}</h5>
                                <div class="mt-3 grid gap-3"><div><div class="text-xs font-medium uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Ajoutés dans Google Ads</div><div class="mt-1 flex flex-wrap gap-1.5">@forelse($change['added'] as $word)<span class="rounded-full bg-emerald-100 px-2 py-1 text-xs text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100">{{ $word }}</span>@empty<span class="text-sm text-gray-500">—</span>@endforelse</div></div>
                                <div><div class="text-xs font-medium uppercase tracking-wide text-rose-700 dark:text-rose-300">Présents seulement dans Cremona</div><div class="mt-1 flex flex-wrap gap-1.5">@forelse($change['missing'] as $word)<span class="rounded-full bg-rose-100 px-2 py-1 text-xs text-rose-900 dark:bg-rose-950 dark:text-rose-100">{{ $word }}</span>@empty<span class="text-sm text-gray-500">—</span>@endforelse</div></div></div>
                            </section>
                        @endforeach
                    </div>
                </article>
            @endforeach
            @if ($identical->isNotEmpty())<details class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-900"><summary class="cursor-pointer font-medium text-gray-800 dark:text-gray-200">{{ $identical->count() }} groupe{{ $identical->count() > 1 ? 's' : '' }} identique{{ $identical->count() > 1 ? 's' : '' }} à Cremona</summary><div class="mt-3 grid gap-2">@foreach($identical as $group)<div class="flex items-center justify-between text-sm text-gray-700 dark:text-gray-300"><span>{{ $group['name'] }}</span><span class="text-emerald-700 dark:text-emerald-300">Identique</span></div>@endforeach</div></details>@endif
            @if ($groups->isEmpty())<p class="text-sm text-gray-600 dark:text-gray-300">Google Ads n’a renvoyé aucun groupe d’annonces pour cette campagne.</p>@endif
        </div>
    @endif
</x-dynamic-component>
