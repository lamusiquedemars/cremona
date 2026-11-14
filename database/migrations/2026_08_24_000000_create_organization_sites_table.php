<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_sites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('base_url')->nullable();
            $table->string('status')->default('active');
            $table->string('integration_key')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('organization_sites'); }
};
