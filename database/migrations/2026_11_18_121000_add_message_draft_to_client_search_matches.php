<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_search_matches', function (Blueprint $table): void {
            $table->text('draft_message')->nullable()->after('status');
            $table->foreignId('prepared_user_id')->nullable()->after('draft_message')->constrained('users')->nullOnDelete();
            $table->timestamp('prepared_at')->nullable()->after('prepared_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('client_search_matches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('prepared_user_id');
            $table->dropColumn(['draft_message', 'prepared_at']);
        });
    }
};
