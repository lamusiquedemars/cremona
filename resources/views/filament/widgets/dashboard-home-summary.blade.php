<x-filament-widgets::widget>
    <div class="space-y-4">
        <p class="text-sm text-gray-600 dark:text-gray-300">
            <span class="font-semibold text-primary-700 dark:text-primary-300">Bonjour {{ $first_name }}</span>
            <span class="px-1 text-gray-400">·</span>
            <span>{{ ucfirst($date) }}</span>
            <span class="px-1 text-gray-400">·</span>
            <span>{{ $status }}</span>
        </p>

        @if (count($overview))
            <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedSquares2x2" class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                    Vue générale
                </div>
                <dl class="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($overview as $item)
                        <div class="flex items-start gap-3 rounded-lg bg-gray-50 px-3 py-3 dark:bg-gray-800">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-primary-700 dark:bg-primary-950 dark:text-primary-300">
                                <x-filament::icon :icon="$item['icon']" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <dt class="text-gray-500 dark:text-gray-400">{{ $item['label'] }}</dt>
                                <dd class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ $item['value'] }}</dd>
                            </div>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif
    </div>
</x-filament-widgets::widget>
