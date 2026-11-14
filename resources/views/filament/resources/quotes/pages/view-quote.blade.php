@push('styles')
    <style>
        .quote-document {
            --quote-ink: #292724;
            --quote-muted: #6b6863;
            --quote-line: #dfdbd4;
            --quote-paper: #fffefa;
            --quote-accent: #353330;
            color: var(--quote-ink);
            background: var(--quote-paper);
            padding: clamp(1.25rem, 3vw, 2.75rem);
        }

        .quote-document-frame {
            padding: clamp(.75rem, 2vw, 2rem);
        }

        .quote-document > .quote-document-header {
            padding: 0 0 clamp(1.5rem, 3vw, 2.5rem) !important;
        }

        .quote-document > section {
            padding: clamp(1.5rem, 3vw, 2.5rem) 0 !important;
        }

        .quote-document > footer {
            padding: clamp(1.25rem, 2vw, 1.75rem) 0 0 !important;
        }

        .quote-document-header {
            background: linear-gradient(135deg, #fffefa 0%, #f8f5ef 100%);
        }

        .quote-document .quote-eyebrow {
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .quote-document .quote-title,
        .quote-document h3 {
            font-family: ui-serif, Georgia, Cambria, "Times New Roman", Times, serif;
        }

        .quote-document .quote-title {
            letter-spacing: -.035em;
        }

        .quote-document .quote-lines thead {
            background: var(--quote-accent);
        }

        .quote-details {
            padding-bottom: clamp(2rem, 4vw, 3.25rem) !important;
        }

        .quote-lines-container {
            margin-top: 1.5rem;
            overflow-x: auto;
            border: 1px solid var(--quote-line);
            border-radius: .625rem;
        }

        .quote-document .quote-lines {
            width: 100%;
            min-width: 44rem;
            border-collapse: collapse;
            font-size: .9375rem;
            line-height: 1.55;
        }

        .quote-document .quote-lines th {
            padding: .9rem 1rem;
            color: #fff;
            font-weight: 600;
            text-align: left;
            white-space: nowrap;
        }

        .quote-document .quote-lines td {
            padding: 1.1rem 1rem;
            vertical-align: top;
            border-bottom: 1px solid var(--quote-line);
        }

        .quote-document .quote-lines .quote-cell-type {
            width: 10rem;
            color: var(--quote-muted);
        }

        .quote-document .quote-lines .quote-cell-description {
            min-width: 20rem;
            white-space: pre-line;
        }

        .quote-document .quote-lines .quote-cell-amount,
        .quote-document .quote-lines .quote-cell-quantity {
            text-align: right;
            white-space: nowrap;
        }

        .quote-document .quote-lines .quote-cell-amount {
            width: 9rem;
        }

        .quote-document .quote-lines .quote-cell-quantity {
            width: 6.5rem;
        }

        .quote-document .quote-lines td:last-child {
            font-weight: 600;
        }

        .quote-document .quote-lines tbody tr:last-child td {
            border-bottom: 0;
        }

        .quote-document .quote-empty-lines {
            padding: 2rem !important;
            color: var(--quote-muted);
            text-align: center;
        }

        .quote-totals {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 24.5rem;
            gap: 2rem;
            align-items: end;
        }

        .quote-summary {
            width: 100%;
            margin: 0;
            padding-right: 1rem;
        }

        .quote-total-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 9rem;
            gap: 1rem;
            align-items: baseline;
            padding: .3rem 0;
        }

        .quote-total-row dt,
        .quote-total-row dd {
            margin: 0;
            text-align: right;
        }

        .quote-total-row dd {
            white-space: nowrap;
        }

        .quote-document .quote-total-final {
            color: var(--quote-accent);
            margin-top: .45rem;
            padding-top: .85rem;
            border-top: 1px solid var(--quote-line);
        }

        @media (max-width: 48rem) {
            .quote-totals {
                grid-template-columns: 1fr;
            }

            .quote-summary {
                max-width: 24.5rem;
                margin-left: auto;
            }
        }

        @media print {
            @page {
                size: A4;
                margin: 13mm;
            }

            .fi-topbar-ctn,
            .fi-sidebar,
            .fi-page-header-main-ctn,
            .fi-breadcrumbs,
            .fi-header-actions-ctn,
            .fi-no {
                display: none !important;
            }

            html,
            body.fi-body,
            .fi-layout,
            .fi-main-ctn,
            .fi-main,
            .fi-page,
            .fi-page-main,
            .fi-page-content {
                display: block !important;
                min-height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
                color: #111 !important;
            }

            .quote-document {
                max-width: none !important;
                overflow: visible !important;
                padding: 0 !important;
                border: 0 !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                color: #111 !important;
                background: #fff !important;
            }

            .quote-document-frame {
                padding: 0 !important;
            }

            .quote-document * {
                color: #111 !important;
                border-color: #bbb !important;
                background: transparent !important;
                box-shadow: none !important;
            }

            .quote-document .quote-lines thead,
            .quote-document .quote-lines thead * {
                color: #fff !important;
                background: #353330 !important;
            }

            .quote-document .quote-lines,
            .quote-document .quote-conditions,
            .quote-document .quote-totals {
                break-inside: avoid;
            }
        }
    </style>
@endpush

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

    <div class="quote-document-frame">
    <article class="quote-document mx-auto max-w-6xl overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <header class="quote-document-header border-b border-gray-200 px-6 py-6 sm:px-8 md:px-10 md:py-8 dark:border-white/10">
            <div class="flex flex-wrap items-start justify-between gap-5">
                <div>
                    <p class="quote-eyebrow text-sm font-medium text-gray-500 dark:text-gray-400">{{ $quote->organization->name }}</p>
                    <h2 class="quote-title mt-1 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white sm:text-3xl">Devis {{ $quote->reference }}</h2>
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

        <section class="quote-details px-6 py-7 sm:px-8 md:px-10">
            <h3 class="text-xl font-semibold tracking-tight text-gray-950 dark:text-white">Détail</h3>

            <div class="quote-lines-container">
                <table class="quote-lines">
                    <colgroup>
                        <col class="quote-cell-type">
                        <col class="quote-cell-description">
                        <col class="quote-cell-amount">
                        <col class="quote-cell-quantity">
                        <col class="quote-cell-amount">
                    </colgroup>
                    <thead class="bg-gray-800 text-left text-sm font-medium text-white dark:bg-black/30">
                        <tr>
                            <th scope="col" class="quote-cell-type">Type</th>
                            <th scope="col" class="quote-cell-description">Description</th>
                            <th scope="col" class="quote-cell-amount">Prix unitaire</th>
                            <th scope="col" class="quote-cell-quantity">Quantité</th>
                            <th scope="col" class="quote-cell-amount">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-white/10 dark:bg-gray-900">
                        @forelse ($quote->lines as $line)
                            <tr>
                                <td class="quote-cell-type">{{ $lineKinds[$line->kind] ?? $line->kind ?? '—' }}</td>
                                <td class="quote-cell-description">{{ $line->description }}</td>
                                <td class="quote-cell-amount">{{ $money($line->unit_amount) }}</td>
                                <td class="quote-cell-quantity">{{ rtrim(rtrim(number_format((float) $line->quantity, 2, ',', ' '), '0'), ',') }}</td>
                                <td class="quote-cell-amount font-medium">{{ $money($line->total_amount) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="quote-empty-lines">Aucune ligne dans ce devis.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="quote-totals mt-6 flex flex-col gap-6 border-t border-gray-200 pt-5 md:flex-row md:items-end md:justify-between dark:border-white/10">
                <p class="max-w-xl whitespace-pre-line text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $quote->tax_note ?: 'TVA et conditions fiscales à préciser si nécessaire.' }}</p>
                <dl class="quote-summary w-full max-w-sm space-y-2 text-sm">
                    <div class="quote-total-row"><dt class="font-medium text-gray-700 dark:text-gray-200">Total</dt><dd class="font-medium text-gray-950 dark:text-white">{{ $money($quote->subtotal_amount) }}</dd></div>
                    @if ((float) $quote->discount_amount > 0)
                        <div class="quote-total-row"><dt class="font-medium text-gray-700 dark:text-gray-200">Remise globale</dt><dd class="font-medium text-gray-950 dark:text-white">− {{ $money($quote->discount_amount) }}</dd></div>
                    @endif
                    <div class="quote-total-row quote-total-final text-base"><dt class="font-semibold text-gray-950 dark:text-white">Total final</dt><dd class="text-lg font-semibold text-gray-950 dark:text-white">{{ $money($quote->total_amount) }}</dd></div>
                </dl>
            </div>
        </section>

        <section class="quote-conditions grid gap-8 border-t border-gray-200 px-6 py-7 sm:px-8 md:grid-cols-2 md:px-10 dark:border-white/10">
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
    </div>
</x-filament-panels::page>
