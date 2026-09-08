<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_order_services', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workshop_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('service_definition_id')->nullable()->constrained()->nullOnDelete();
            $t->string('label_snapshot');
            $t->text('description_snapshot')->nullable();
            $t->decimal('quantity', 10, 2)->default(1);
            $t->decimal('unit_amount', 12, 2)->default(0);
            $t->boolean('include_in_quote')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_order_services');
    }
};
