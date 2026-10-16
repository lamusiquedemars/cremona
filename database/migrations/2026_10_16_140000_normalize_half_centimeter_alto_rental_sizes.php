<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const REMOVED_ALTO_SIZES = [
        '40_5cm' => '41cm',
        '41_5cm' => '42cm',
    ];

    public function up(): void
    {
        DB::table('instrument_assets')
            ->where('family', 'alto')
            ->whereIn('rental_size', array_keys(self::REMOVED_ALTO_SIZES))
            ->orderBy('id')
            ->each(function (object $instrument): void {
                DB::table('instrument_assets')->where('id', $instrument->id)->update([
                    'rental_size' => self::REMOVED_ALTO_SIZES[$instrument->rental_size],
                    'updated_at' => now(),
                ]);
            });

        foreach (['instrument_categories', 'rental_insurance_plans'] as $table) {
            DB::table($table)->where('family', 'alto')->orderBy('id')->each(function (object $record) use ($table): void {
                $sizes = json_decode($record->eligible_sizes ?? '[]', true);
                $normalized = array_values(array_unique(array_map(
                    fn (string $size): string => self::REMOVED_ALTO_SIZES[$size] ?? $size,
                    is_array($sizes) ? $sizes : [],
                )));

                DB::table($table)->where('id', $record->id)->update([
                    'eligible_sizes' => json_encode($normalized),
                    'updated_at' => now(),
                ]);
            });
        }
    }

    public function down(): void
    {
        // La fusion vers des tailles entières est volontairement irréversible.
    }
};
