<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instrument_assets', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->string('reference', 80)->nullable();
            $t->string('name');
            $t->string('family', 80)->nullable();
            $t->string('maker')->nullable();
            $t->string('year', 20)->nullable();
            $t->string('ownership', 32)->default('owned');
            $t->string('status', 32)->default('available');
            $t->text('description')->nullable();
            $t->boolean('available_for_sale')->default(false);
            $t->boolean('available_for_rental')->default(false);
            $t->decimal('suggested_sale_amount', 12, 2)->default(0);
            $t->decimal('suggested_rental_amount', 12, 2)->default(0);
            $t->timestamps();
            $t->unique(['organization_id', 'reference']);
            $t->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instrument_assets');
    }
};
