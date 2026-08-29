<div class="space-y-6 text-sm text-gray-950 dark:text-gray-100">
    <header class="border-b border-gray-200 pb-5 dark:border-white/10">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-base font-semibold tracking-tight">{{ $preview['campaign']['name'] }}</p>
                <p class="mt-1 text-gray-600 dark:text-gray-300">Contrôle avant création dans Google Ads.</p>
            </div>
            <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900 ring-1 ring-amber-200 dark:bg-amber-400/15 dark:text-amber-200 dark:ring-amber-300/25">
                Google Search · Création en pause
            </span>
        </div>
    </header>

    <section class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/[0.05]">
        <p class="text-xs font-semibold tracking-wider text-gray-600 uppercase dark:text-gray-300">Destination et suivi</p>
        <a href="{{ $preview['campaign']['final_url'] }}" target="_blank" rel="noopener noreferrer" class="mt-2 block break-all font-medium text-amber-800 underline decoration-amber-400/70 underline-offset-4 hover:text-amber-950 dark:text-amber-200 dark:hover:text-amber-100">
            {{ $preview['campaign']['final_url'] }}
        </a>
    </section>

    <dl class="grid gap-3 sm:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
            <dt class="text-xs font-semibold tracking-wider text-gray-600 uppercase dark:text-gray-300">Budget quotidien</dt>
            <dd class="mt-2 text-lg font-semibold">{{ $preview['campaign']['daily_budget'] }} {{ $preview['campaign']['currency'] }}</dd>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
            <dt class="text-xs font-semibold tracking-wider text-gray-600 uppercase dark:text-gray-300">Conversion</dt>
            <dd class="mt-2 font-semibold">{{ $preview['campaign']['conversion_goal'] }}</dd>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
            <dt class="text-xs font-semibold tracking-wider text-gray-600 uppercase dark:text-gray-300">Zones ciblées</dt>
            <dd class="mt-2 font-medium">{{ implode(' · ', $preview['campaign']['target_locations']) }}</dd>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
            <dt class="text-xs font-semibold tracking-wider text-gray-600 uppercase dark:text-gray-300">Clé UTM</dt>
            <dd class="mt-2 break-all font-medium">{{ $preview['campaign']['tracking_key'] }}</dd>
        </div>
    </dl>

    <section>
        <div class="flex items-baseline justify-between gap-3">
            <h3 class="text-base font-semibold tracking-tight">Groupes d’annonces</h3>
            <span class="text-xs text-gray-600 dark:text-gray-300">{{ count($preview['ad_groups']) }} groupe(s)</span>
        </div>

        <div class="mt-3 space-y-3">
            @foreach ($preview['ad_groups'] as $group)
                <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/[0.04]">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <p class="font-semibold">{{ $group['name'] }}</p>
                        <span class="text-xs text-gray-600 dark:text-gray-300">Prêt à créer</span>
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-4">
                        <div><dt class="text-xs text-gray-600 dark:text-gray-300">Mots-clés</dt><dd class="mt-1 font-semibold">{{ count($group['keywords']) }}</dd></div>
                        <div><dt class="text-xs text-gray-600 dark:text-gray-300">Exclusions</dt><dd class="mt-1 font-semibold">{{ count($group['negative_keywords']) }}</dd></div>
                        <div><dt class="text-xs text-gray-600 dark:text-gray-300">Titres</dt><dd class="mt-1 font-semibold">{{ count($group['headlines']) }}</dd></div>
                        <div><dt class="text-xs text-gray-600 dark:text-gray-300">Descriptions</dt><dd class="mt-1 font-semibold">{{ count($group['descriptions']) }}</dd></div>
                    </dl>
                </section>
            @endforeach
        </div>
    </section>
</div>
