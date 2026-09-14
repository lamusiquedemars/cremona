<x-filament-panels::page>
    @php
        $recipient = $quote->person?->display_name ?? $quote->company?->legal_name ?? $quote->company?->name;
        $recipientEmail = $quote->person?->contactMethods->firstWhere('type', 'email')?->value
            ?? $quote->company?->contactMethods->firstWhere('type', 'email')?->value;
        $status = [
            'draft' => ['Brouillon', 'bg-gray-100 text-gray-700 ring-gray-200 dark:bg-white/10 dark:text-gray-200 dark:ring-white/20'],
            'sent' => ['Envoyé', 'bg-sky-100 text-sky-800 ring-sky-200 dark:bg-sky-400/15 dark:text-sky-200 dark:ring-sky-300/25'],
            'accepted' => ['Accepté', 'bg-emerald-100 text-emerald-800 ring-emerald-200 dark:bg-emerald-400/15 dark:text-emerald-200 dark:ring-emerald-300/25'],
            'declined' => ['Refusé', 'bg-rose-100 text-rose-800 ring-rose-200 dark:bg-rose-400/15 dark:text-rose-200 dark:ring-rose-300/25'],
            'expired' => ['Expiré', 'bg-amber-100 text-amber-800 ring-amber-200 dark:bg-amber-400/15 dark:text-amber-200 dark:ring-amber-300/25'],
        ][$quote->status->value];
        $lineKinds = [
            'service' => 'Prestation',
            'product' => 'Produit',
            'rental' => 'Location',
            'fee' => 'Frais',
        ];
        $money = fn (mixed $amount): string => \Illuminate\Support\Number::currency((float) $amount, in: $quote->currency, locale: 'fr');
    @endphp

    <article class="mx-auto max-w-6xl overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <header class="border-b border-gray-200 px-6 py-6 sm:px-8 md:px-10 md:py-8 dark:border-white/10">
            <div class="flex flex-wrap items-start justify-between gap-5">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $quote->organization->name }}</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white sm:text-3xl">Devis {{ $quote->reference }}</h2>
                    <p class="mt-1 text-base text-gray-600 dark:text-gray-300">{{ $quote->title }}</p>
                </div>
                <div class="text-left sm:text-right">
                    <span class="inline-flex rounded-full px-3 py-1 text-sm font-semibold ring-1 ring-inset {{ $status[1] }}">{{ $status[0] }}</span>
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                        Émis le {{ $quote->issued_on?->translatedFormat('d F Y') ?? '—' }}
                    </p>
                    @if ($quote->valid_until)
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Valable jusqu’au {{ $quote->valid_until->translatedFormat('d F Y') }}</p>
                    @endif
                </div>
            </div>
        </header>

        <section class="grid gap-8 border-b border-gray-200 px-6 py-7 sm:px-8 md:grid-cols-2 md:px-10 dark:border-white/10">
            <div>
                <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Émetteur</h3>
                <p class="mt-3 font-medium text-gray-800 dark:text-gray-100">{{ $quote->organization->name }}</p>
                <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">Organisation émettrice du devis</p>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Destinataire</h3>
                @if ($recipient)
                    <p class="mt-3 font-medium text-gray-800 dark:text-gray-100">{{ $recipient }}</p>
                    @if ($recipientEmail)
                        <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $recipientEmail }}</p>
                    @endif
                @else
                    <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">Aucun destinataire n’est encore renseigné.</p>
                @endif
            </div>
        </section>

        @if (filled($quote->introduction))
            <section class="border-b border-gray-200 px-6 py-6 sm:px-8 md:px-10 dark:border-white/10">
                <p class="whitespace-pre-line text-sm leading-7 text-gray-700 dark:text-gray-200">{{ $quote->introduction }}</p>
            </section>
        @endif

        <section class="px-6 py-7 sm:px-8 md:px-10">
            <h3 class="text-xl font-semibold tracking-tight text-gray-950 dark:text-white">Détail</h3>

            <div class="mt-5 overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead class="bg-gray-800 text-left text-sm font-medium text-white dark:bg-black/30">
                        <tr>
                            <th scope="col" class="w-28 px-4 py-3 font-medium">Type</th>
                            <th scope="col" class="min-w-80 px-4 py-3 font-medium">Description</th>
                            <th scope="col" class="w-32 px-4 py-3 text-right font-medium">Prix unitaire</th>
                            <th scope="col" class="w-24 px-4 py-3 text-right font-medium">Quantité</th>
                            <th scope="col" class="w-32 px-4 py-3 text-right font-medium">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-white/10 dark:bg-gray-900">
                        @forelse ($quote->lines as $line)
                            <tr>
                                <td class="px-4 py-4 align-top text-gray-600 dark:text-gray-300">{{ $lineKinds[$line->kind] ?? $line->kind ?? '—' }}</td>
                                <td class="whitespace-pre-line px-4 py-4 align-top leading-6 text-gray-900 dark:text-white">{{ $line->description }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-right align-top text-gray-700 dark:text-gray-200">{{ $money($line->unit_amount) }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-right align-top text-gray-700 dark:text-gray-200">{{ rtrim(rtrim(number_format((float) $line->quantity, 2, ',', ' '), '0'), ',') }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-right align-top font-medium text-gray-950 dark:text-white">{{ $money($line->total_amount) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Aucune ligne dans ce devis.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex flex-col gap-6 border-t border-gray-200 pt-5 md:flex-row md:items-end md:justify-between dark:border-white/10">
                <p class="max-w-xl whitespace-pre-line text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $quote->tax_note ?: 'TVA et conditions fiscales à préciser si nécessaire.' }}</p>
                <dl class="w-full max-w-sm space-y-2 text-sm">
                    <div class="flex items-baseline justify-between gap-6"><dt class="font-medium text-gray-700 dark:text-gray-200">Total</dt><dd class="font-medium text-gray-950 dark:text-white">{{ $money($quote->subtotal_amount) }}</dd></div>
                    @if ((float) $quote->discount_amount > 0)
                        <div class="flex items-baseline justify-between gap-6"><dt class="font-medium text-gray-700 dark:text-gray-200">Remise globale</dt><dd class="font-medium text-gray-950 dark:text-white">− {{ $money($quote->discount_amount) }}</dd></div>
                    @endif
                    <div class="flex items-baseline justify-between gap-6 border-t border-gray-300 pt-3 text-base dark:border-white/20"><dt class="font-semibold text-gray-950 dark:text-white">Total final</dt><dd class="text-lg font-semibold text-gray-950 dark:text-white">{{ $money($quote->total_amount) }}</dd></div>
                </dl>
            </div>
        </section>

        <section class="grid gap-8 border-t border-gray-200 px-6 py-7 sm:px-8 md:grid-cols-2 md:px-10 dark:border-white/10">
            <div>
                <h3 class="text-xl font-semibold tracking-tight text-gray-950 dark:text-white">Conditions</h3>
                <dl class="mt-4 space-y-2 text-sm leading-6 text-gray-700 dark:text-gray-200">
                    <div><dt class="inline font-semibold">Conditions de règlement : </dt><dd class="inline whitespace-pre-line">{{ $quote->payment_terms ?: 'À préciser.' }}</dd></div>
                    <div><dt class="inline font-semibold">Validité du devis : </dt><dd class="inline">{{ $quote->valid_until ? 'jusqu’au '.$quote->valid_until->translatedFormat('d F Y') : 'à préciser.' }}</dd></div>
                </dl>
            </div>
            <div>
                <h3 class="text-xl font-semibold tracking-tight text-gray-950 dark:text-white">Bon pour accord</h3>
                <div class="mt-5 space-y-5 text-sm text-gray-700 dark:text-gray-200">
                    <p>À <span class="mx-2 inline-block w-36 border-b border-gray-300 dark:border-white/20"></span>, le <span class="mx-2 inline-block w-28 border-b border-gray-300 dark:border-white/20"></span></p>
                    <p>Signature et cachet</p>
                    <div class="h-12 border-b border-gray-200 dark:border-white/10"></div>
                </div>
            </div>
        </section>

        <footer class="border-t border-gray-200 px-6 py-4 text-xs text-gray-500 sm:px-8 md:px-10 dark:border-white/10 dark:text-gray-400">
            {{ $quote->reference }} · Document préparé le {{ $quote->updated_at->translatedFormat('d F Y') }}
        </footer>
    </article>
</x-filament-panels::page>
