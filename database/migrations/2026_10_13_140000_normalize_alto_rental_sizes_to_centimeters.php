<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ALTO_SIZES = [
        '13in' => '38cm',
        '14in' => '39cm',
        '15in' => '40cm',
        '15_5in' => '40_5cm',
        '16in' => '41cm',
        '16_5in' => '41_5cm',
    ];

    public function up(): void
    {
        DB::table('instrument_assets')
            ->where('family', 'alto')
            ->whereIn('rental_size', array_keys(self::ALTO_SIZES))
            ->orderBy('id')
            ->each(function (object $instrument): void {
                DB::table('instrument_assets')->where('id', $instrument->id)->update([
                    'rental_size' => self::ALTO_SIZES[$instrument->rental_size],
                    'updated_at' => now(),
                ]);
            });

        foreach (['instrument_categories', 'rental_insurance_plans'] as $table) {
            DB::table($table)->where('family', 'alto')->orderBy('id')->each(function (object $record) use ($table): void {
                $sizes = json_decode($record->eligible_sizes ?? '[]', true);
                $normalized = array_values(array_unique(array_map(
                    fn (string $size): string => self::ALTO_SIZES[$size] ?? $size,
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
        $reverse = array_flip(self::ALTO_SIZES);

        DB::table('instrument_assets')
            ->where('family', 'alto')
            ->whereIn('rental_size', array_keys($reverse))
            ->orderBy('id')
            ->each(function (object $instrument) use ($reverse): void {
                DB::table('instrument_assets')->where('id', $instrument->id)->update([
                    'rental_size' => $reverse[$instrument->rental_size],
                    'updated_at' => now(),
                ]);
            });

        foreach (['instrument_categories', 'rental_insurance_plans'] as $table) {
            DB::table($table)->where('family', 'alto')->orderBy('id')->each(function (object $record) use ($table, $reverse): void {
                $sizes = json_decode($record->eligible_sizes ?? '[]', true);
                $normalized = array_values(array_unique(array_map(
                    fn (string $size): string => $reverse[$size] ?? $size,
                    is_array($sizes) ? $sizes : [],
                )));

                DB::table($table)->where('id', $record->id)->update([
                    'eligible_sizes' => json_encode($normalized),
                    'updated_at' => now(),
                ]);
            });
        }
    }
};
