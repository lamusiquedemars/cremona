<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('organization_user')
            ->where('role', 'administrator')
            ->update([
                'role' => 'owner',
                'permissions' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // The former administrator role is deliberately consolidated into owner.
    }
};
