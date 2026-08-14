<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_integrations', function (Blueprint $table): void {
            $table->ulid('key_id')->nullable()->unique()->after('credentials');
            $table->string('token_hash', 64)->nullable()->after('key_id');
        });
    }

    public function down(): void
    {
        Schema::table('organization_integrations', function (Blueprint $table): void {
            $table->dropUnique(['key_id']);
            $table->dropColumn(['key_id', 'token_hash']);
        });
    }
};
