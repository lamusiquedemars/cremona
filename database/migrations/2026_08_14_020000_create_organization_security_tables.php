<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The migration can safely resume if a deployment was interrupted after
        // creating one of its tables but before Laravel recorded the migration.
        if (! Schema::hasTable('organization_audit_logs')) {
            Schema::create('organization_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event')->index();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['organization_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('organization_integrations')) {
            Schema::create('organization_integrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('name')->default('default');
            $table->longText('credentials');
            $table->string('status')->default('active')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'provider', 'name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_integrations');
        Schema::dropIfExists('organization_audit_logs');
    }
};
