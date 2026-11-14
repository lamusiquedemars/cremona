<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('private_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('version_of_id')->nullable()->constrained('private_documents')->nullOnDelete();
            $table->unsignedSmallInteger('version_number')->default(1);
            $table->string('title')->nullable();
            $table->string('category', 100)->nullable();
            $table->string('disk', 32);
            $table->string('path');
            $table->string('original_name');
            $table->string('declared_mime_type', 255)->nullable();
            $table->string('detected_mime_type', 255)->nullable();
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->timestamps();

            $table->unique(['disk', 'path']);
            $table->index(['organization_id', 'category', 'created_at']);
            $table->index(['organization_id', 'version_of_id']);
        });

        Schema::create('private_document_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('private_document_id')->constrained()->cascadeOnDelete();
            $table->morphs('linkable');
            $table->timestamps();

            $table->unique(['private_document_id', 'linkable_type', 'linkable_id'], 'private_document_links_unique_link');
            $table->index(['organization_id', 'linkable_type', 'linkable_id'], 'private_document_links_tenant_link');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('private_document_links');
        Schema::dropIfExists('private_documents');
    }
};
