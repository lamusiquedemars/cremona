<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->string('played_instrument', 80)->nullable()->after('display_name');
            $table->string('school_name')->nullable()->after('played_instrument');
            $table->string('musical_level', 80)->nullable()->after('school_name');
        });

        Schema::create('client_searches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->string('object_type', 48);
            $table->string('size', 32)->nullable();
            $table->decimal('maximum_budget', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'object_type']);
            $table->index(['person_id', 'status']);
        });

        Schema::create('availabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('object_type', 48);
            $table->string('size', 32)->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('source', 24)->default('workshop');
            $table->string('source_detail')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('available');
            $table->timestamp('available_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'object_type']);
        });

        Schema::create('client_search_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_search_id')->constrained()->cascadeOnDelete();
            $table->foreignId('availability_id')->constrained()->cascadeOnDelete();
            $table->string('match_kind', 24);
            $table->string('status', 24)->default('suggested');
            $table->timestamp('contacted_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->unique(['client_search_id', 'availability_id']);
            $table->index(['organization_id', 'status', 'match_kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_search_matches');
        Schema::dropIfExists('availabilities');
        Schema::dropIfExists('client_searches');
        Schema::table('people', function (Blueprint $table): void {
            $table->dropColumn(['played_instrument', 'school_name', 'musical_level']);
        });
    }
};
