<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('platform_settings');
    }

    public function down(): void
    {
        Schema::create('platform_settings', function ($table): void {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value');
            $table->timestamps();
        });
    }
};
