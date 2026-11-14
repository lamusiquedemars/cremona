<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Quote;
use App\Models\WorkshopOrder;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

class QuoteNumberGenerator
{
    public function next(int $organizationId, ?DateTimeInterface $createdAt = null): string
    {
        return DB::transaction(function () use ($organizationId, $createdAt): string {
            // Serialize allocations, including the first number of the year.
            Organization::query()->whereKey($organizationId)->lockForUpdate()->firstOrFail();
            $date = $createdAt ? CarbonImmutable::instance($createdAt) : now();
            $year = (int) $date->format('Y');
            $prefix = 'D'.$date->format('y');
            $sequence = DB::table('quote_number_sequences')
                ->where('organization_id', $organizationId)->where('year', $year);
            $last = (int) (clone $sequence)->value('last_number');

            // Keep existing/imported references; start beyond their largest number.
            $existing = Quote::withoutGlobalScopes()->where('organization_id', $organizationId)
                ->where('reference', 'like', $prefix.'____')->pluck('reference');
            foreach ($existing as $reference) {
                if (preg_match('/^'.preg_quote($prefix, '/').'([0-9]{4})$/', $reference, $matches)) {
                    $last = max($last, (int) $matches[1]);
                }
            }

            $orders = WorkshopOrder::withoutGlobalScopes()->where('organization_id', $organizationId)
                ->where('reference', 'like', 'AT'.$date->format('y').'____')->pluck('reference');
            foreach ($orders as $reference) {
                if (preg_match('/^AT'.$date->format('y').'([0-9]{4})$/', $reference, $matches)) {
                    $last = max($last, (int) $matches[1]);
                }
            }

            if ($last >= 9999) {
                throw new LogicException('Le compteur annuel des devis a atteint 9999. Aucun nouveau numéro ne peut être attribué dans ce format.');
            }

            DB::table('quote_number_sequences')->updateOrInsert(
                ['organization_id' => $organizationId, 'year' => $year],
                ['last_number' => $last + 1],
            );

            return $prefix.sprintf('%04d', $last + 1);
        }, 5);
    }
}
