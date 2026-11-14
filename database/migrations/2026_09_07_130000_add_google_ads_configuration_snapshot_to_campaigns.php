<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->json('google_ads_configuration')->nullable()->after('configuration');
            $table->timestamp('google_ads_configuration_synced_at')->nullable()->after('google_ads_configuration');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropColumn(['google_ads_configuration', 'google_ads_configuration_synced_at']);
        });
    }
};
