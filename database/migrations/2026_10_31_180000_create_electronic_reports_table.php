<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('electronic_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('source');
            $table->string('kind', 32);
            $table->string('scope', 32);
            $table->string('status', 32)->default('pending_review');
            $table->date('occurred_on');
            $table->decimal('amount', 14, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->json('payload');
            $table->string('remote_id', 128)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['source_type', 'source_id', 'kind'], 'electronic_reports_source_kind_unique');
            $table->index(['organization_id', 'status', 'occurred_on'], 'electronic_reports_scope_status_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('electronic_reports');
    }
};
