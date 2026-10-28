<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('status', 20)->default('active')->index()->after('is_platform_admin');
            $table->timestamp('invited_at')->nullable()->after('status');
            $table->timestamp('last_login_at')->nullable()->after('invited_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'invited_at', 'last_login_at']);
        });
    }
};
