<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->foreignId('rental_id')->constrained()->restrictOnDelete();
            $table->foreignId('private_document_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('version_of_id')->nullable()->constrained('rental_documents')->restrictOnDelete();
            $table->unsignedSmallInteger('version_number')->default(1);
            $table->string('type', 48);
            $table->string('status', 32)->default('draft');
            $table->string('template_version', 100)->nullable();
            $table->json('snapshot')->nullable();
            $table->char('content_sha256', 64)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['rental_id', 'type', 'version_number']);
            $table->index(['organization_id', 'rental_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_documents');
    }
};
