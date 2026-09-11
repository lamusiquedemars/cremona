<x-filament-widgets::widget>
    <div class="space-y-3">
        <p class="text-sm text-gray-600 dark:text-gray-300">
            <span class="font-semibold text-gray-950 dark:text-white">Bonjour {{ $first_name }}</span>
            <span class="px-1 text-gray-400">·</span>
            {{ $status }}
        </p>

        @if (count($overview))
            <x-filament::section heading="Vue générale" compact>
                <dl class="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($overview as $item)
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">{{ $item['label'] }}</dt>
                            <dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $item['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-filament::section>
        @endif
    </div>
</x-filament-widgets::widget>
