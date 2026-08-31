<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->string('google_ads_status', 32)->nullable()->after('external_reference');
            $table->string('google_ads_primary_status', 32)->nullable()->after('google_ads_status');
            $table->json('google_ads_primary_status_reasons')->nullable()->after('google_ads_primary_status');
            $table->string('google_ads_serving_status', 32)->nullable()->after('google_ads_primary_status_reasons');
            $table->string('google_ads_bidding_status', 48)->nullable()->after('google_ads_serving_status');
            $table->timestamp('google_ads_synced_at')->nullable()->after('google_ads_bidding_status');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropColumn([
                'google_ads_status',
                'google_ads_primary_status',
                'google_ads_primary_status_reasons',
                'google_ads_serving_status',
                'google_ads_bidding_status',
                'google_ads_synced_at',
            ]);
        });
    }
};
