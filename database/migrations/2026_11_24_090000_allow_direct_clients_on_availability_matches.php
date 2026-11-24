<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_search_matches', function (Blueprint $table): void {
            $table->foreignId('person_id')->nullable()->after('client_search_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_search_id')->nullable()->change();
            $table->unique(['availability_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::table('client_search_matches', function (Blueprint $table): void {
            $table->dropUnique(['availability_id', 'person_id']);
            $table->dropConstrainedForeignId('person_id');
        });
    }
};
