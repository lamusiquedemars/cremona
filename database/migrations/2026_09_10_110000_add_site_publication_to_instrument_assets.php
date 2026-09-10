<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instrument_assets', function (Blueprint $table): void {
            $table->boolean('is_site_published')->default(false)->after('available_for_rental');
            $table->string('public_slug')->nullable()->after('is_site_published');
            $table->string('public_title')->nullable()->after('public_slug');
            $table->text('public_description')->nullable()->after('public_title');
            $table->string('public_price_label')->nullable()->after('public_description');
            $table->timestamp('site_published_at')->nullable()->after('public_price_label');
            $table->timestamp('site_last_published_at')->nullable()->after('site_published_at');
            $table->text('site_last_publication_error')->nullable()->after('site_last_published_at');
            $table->unique(['organization_id', 'public_slug']);
        });
    }

    public function down(): void
    {
        Schema::table('instrument_assets', function (Blueprint $table): void {
            $table->dropUnique(['organization_id', 'public_slug']);
            $table->dropColumn(['is_site_published', 'public_slug', 'public_title', 'public_description', 'public_price_label', 'site_published_at', 'site_last_published_at', 'site_last_publication_error']);
        });
    }
};
