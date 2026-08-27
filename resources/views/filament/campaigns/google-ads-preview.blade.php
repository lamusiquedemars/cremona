<div class="space-y-5 text-sm">
    <div>
        <p class="font-medium">{{ $preview['campaign']['name'] }}</p>
        <p class="text-gray-600 dark:text-gray-400">Google Search · Statut de création : <strong>PAUSED</strong></p>
    </div>

    <dl class="grid gap-3 sm:grid-cols-2">
        <div><dt class="text-gray-500">URL finale</dt><dd>{{ $preview['campaign']['final_url'] }}</dd></div>
        <div><dt class="text-gray-500">Budget quotidien</dt><dd>{{ $preview['campaign']['daily_budget'] }} {{ $preview['campaign']['currency'] }}</dd></div>
        <div><dt class="text-gray-500">Conversion</dt><dd>{{ $preview['campaign']['conversion_goal'] }}</dd></div>
        <div><dt class="text-gray-500">Clé UTM</dt><dd>{{ $preview['campaign']['tracking_key'] }}</dd></div>
    </dl>

    @foreach ($preview['ad_groups'] as $group)
        <section class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <p class="font-medium">{{ $group['name'] }}</p>
            <p class="mt-2 text-gray-500">{{ count($group['keywords']) }} mot(s)-clé(s), {{ count($group['negative_keywords']) }} exclusion(s), {{ count($group['headlines']) }} titre(s), {{ count($group['descriptions']) }} description(s).</p>
        </section>
    @endforeach
</div>
