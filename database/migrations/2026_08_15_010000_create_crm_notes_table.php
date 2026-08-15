<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->morphs('notable');
            $table->foreignId('author_user_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(
                ['organization_id', 'notable_type', 'notable_id', 'created_at'],
                'crm_notes_timeline',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_notes');
    }
};
