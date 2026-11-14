<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('instrument_assets')
            ->select(['id', 'attributes', 'media'])
            ->orderBy('id')
            ->eachById(function (object $instrument): void {
                $changes = [];

                foreach (['attributes', 'media'] as $field) {
                    $value = $instrument->{$field};
                    $normalized = $this->normalizeArrayJson($value);

                    if ($normalized !== null && $normalized !== $value) {
                        $changes[$field] = $normalized;
                    }
                }

                if ($changes !== []) {
                    DB::table('instrument_assets')->where('id', $instrument->id)->update($changes);
                }
            });
    }

    public function down(): void
    {
        // The normalized JSON is the intended persistent format.
    }

    private function normalizeArrayJson(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            if (! is_string($decoded)) {
                return null;
            }

            $value = $decoded;
        }

        return null;
    }
};
