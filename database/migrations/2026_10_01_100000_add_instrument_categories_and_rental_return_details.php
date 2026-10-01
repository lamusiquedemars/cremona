<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instrument_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->decimal('rental_monthly_amount', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        Schema::table('instrument_assets', function (Blueprint $table): void {
            $table->foreignId('instrument_category_id')->nullable()->after('family')->constrained('instrument_categories')->nullOnDelete();
            $table->text('commercial_notes')->nullable()->after('description');
        });

        Schema::table('rentals', function (Blueprint $table): void {
            $table->text('return_notes')->nullable()->after('returned_on');
            $table->foreignId('returned_by_user_id')->nullable()->after('return_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('returned_at')->nullable()->after('returned_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('returned_by_user_id');
            $table->dropColumn(['return_notes', 'returned_at']);
        });
        Schema::table('instrument_assets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('instrument_category_id');
            $table->dropColumn('commercial_notes');
        });
        Schema::dropIfExists('instrument_categories');
    }
};
