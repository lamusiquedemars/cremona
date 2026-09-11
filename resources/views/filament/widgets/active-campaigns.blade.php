<x-filament-widgets::widget>
    <x-filament::section
        heading="Campagnes actives"
        description="Touchez une carte pour voir le détail."
    >
        @if (count($campaigns))
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($campaigns as $campaign)
                    <a
                        href="{{ $campaign['url'] }}"
                        class="block rounded-xl border border-gray-200 bg-white p-4 transition hover:border-primary-300 hover:bg-primary-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-700 dark:hover:bg-gray-800"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="font-semibold text-gray-950 dark:text-white">{{ $campaign['name'] }}</h3>
                            @if (filled($campaign['google_status']))
                                <span class="shrink-0 text-sm font-medium text-primary-700 dark:text-primary-300">{{ $campaign['google_status'] }}</span>
                            @endif
                        </div>

                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Dépenses</dt>
                                <dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $campaign['spend'] }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Demandes</dt>
                                <dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $campaign['leads'] }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Impressions</dt>
                                <dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $campaign['impressions'] }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Clics</dt>
                                <dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $campaign['clicks'] }}</dd>
                            </div>
                        </dl>

                        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Google Ads : {{ $campaign['synced_at'] }}</p>
                    </a>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
