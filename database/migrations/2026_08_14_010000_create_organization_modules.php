<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_modules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('module');
            $table->boolean('enabled')->default(true);
            $table->json('configuration')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_modules');
    }
};
