<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('private_document_storage_migrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('private_document_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('source_disk', 64);
            $table->string('source_path');
            $table->string('target_disk', 64);
            $table->string('target_path');
            $table->char('sha256', 64);
            $table->unsignedBigInteger('size');
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('private_document_storage_migrations');
    }
};
