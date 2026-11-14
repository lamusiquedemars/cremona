<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instrument_assets', function (Blueprint $table): void {
            $table->json('attributes')->nullable()->after('description');
            $table->json('media')->nullable()->after('attributes');
        });
    }

    public function down(): void
    {
        Schema::table('instrument_assets', function (Blueprint $table): void {
            $table->dropColumn(['attributes', 'media']);
        });
    }
};
