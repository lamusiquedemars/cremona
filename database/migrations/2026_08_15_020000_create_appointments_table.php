<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('incoming_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('status', 24)->default('scheduled');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('timezone', 64)->default('UTC');
            $table->string('modality', 24)->default('video');
            $table->string('location')->nullable();
            $table->string('meeting_url', 2048)->nullable();
            $table->string('provider', 32)->default('manual');
            $table->string('external_reference')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'starts_at']);
            $table->unique(
                ['organization_id', 'provider', 'external_reference'],
                'appointments_external_reference_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
