<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('electronic_reporting_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('remote_id');
            $table->string('kind', 32);
            $table->string('role_code', 8);
            $table->date('start_period')->nullable();
            $table->date('end_period')->nullable();
            $table->json('events')->nullable();
            $table->timestamp('synchronized_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'remote_id']);
            $table->index(['organization_id', 'kind', 'end_period'], 'electronic_reporting_batches_scope_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('electronic_reporting_batches');
    }
};
