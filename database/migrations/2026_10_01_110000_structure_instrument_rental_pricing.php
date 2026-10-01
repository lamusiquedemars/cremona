<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_tiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        Schema::table('instrument_categories', function (Blueprint $table): void {
            $table->string('family', 80)->nullable()->after('name');
            $table->json('eligible_sizes')->nullable()->after('family');
            $table->foreignId('rental_tier_id')->nullable()->after('eligible_sizes')->constrained('rental_tiers')->nullOnDelete();
        });

        Schema::table('instrument_assets', function (Blueprint $table): void {
            $table->string('rental_size', 32)->nullable()->after('family');
            $table->foreignId('rental_tier_id')->nullable()->after('rental_size')->constrained('rental_tiers')->nullOnDelete();
            $table->string('rental_pricing_mode', 16)->default('automatic')->after('rental_tier_id');
            $table->decimal('rental_amount_override', 12, 2)->nullable()->after('rental_pricing_mode');
        });

        // Les anciens prix saisis instrument par instrument deviennent des dérogations explicites.
        DB::table('instrument_assets')
            ->where('suggested_rental_amount', '>', 0)
            ->update([
                'rental_pricing_mode' => 'override',
                'rental_amount_override' => DB::raw('suggested_rental_amount'),
            ]);
    }

    public function down(): void
    {
        Schema::table('instrument_assets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rental_tier_id');
            $table->dropColumn(['rental_size', 'rental_pricing_mode', 'rental_amount_override']);
        });
        Schema::table('instrument_categories', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rental_tier_id');
            $table->dropColumn(['family', 'eligible_sizes']);
        });
        Schema::dropIfExists('rental_tiers');
    }
};
