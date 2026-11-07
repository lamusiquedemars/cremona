<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_sites', function (Blueprint $table): void {
            $table->boolean('site_portal_enabled')->default(false)->after('status');
            $table->string('site_portal_client_id')->nullable()->unique()->after('integration_key');
            $table->text('site_portal_secret')->nullable()->after('site_portal_client_id');
            $table->json('site_portal_items')->nullable()->after('site_portal_secret');
            $table->string('site_portal_logout_redirect_uri')->nullable()->after('site_portal_items');
        });
    }

    public function down(): void
    {
        Schema::table('organization_sites', function (Blueprint $table): void {
            $table->dropUnique(['site_portal_client_id']);
            $table->dropColumn(['site_portal_enabled', 'site_portal_client_id', 'site_portal_secret', 'site_portal_items', 'site_portal_logout_redirect_uri']);
        });
    }
};
