<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const GROUP_KEYS = [
        'customer_follow_up',
        'commercial_activity',
        'workshop',
        'catalog_inventory',
        'marketing',
    ];

    public function up(): void
    {
        DB::table('organizations')->orderBy('id')->each(function (object $organization): void {
            $settings = json_decode($organization->settings ?? '{}', true);

            if (! is_array($settings) || ! isset($settings['presentation']['labels']) || ! is_array($settings['presentation']['labels'])) {
                return;
            }

            foreach (self::GROUP_KEYS as $key) {
                unset($settings['presentation']['labels'][$key]);
            }

            DB::table('organizations')
                ->where('id', $organization->id)
                ->update(['settings' => json_encode($settings)]);
        });
    }

    public function down(): void
    {
        // Les anciens intitulés de chapeaux étaient propres à chaque organisation et ne doivent pas être recréés.
    }
};
