<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rentals', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->string('public_id', 26)->unique();
            $t->string('reference', 40);
            $t->foreignId('instrument_asset_id')->constrained()->restrictOnDelete();
            $t->foreignId('person_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status', 32)->default('draft');
            $t->date('starts_on')->nullable();
            $t->date('expected_return_on')->nullable();
            $t->date('returned_on')->nullable();
            $t->decimal('unit_amount', 12, 2)->default(0);
            $t->decimal('deposit_amount', 12, 2)->default(0);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['organization_id', 'reference']);
            $t->index(['instrument_asset_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};
