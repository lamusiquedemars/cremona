<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('channel', 32);
            $table->string('tracking_key');
            $table->string('external_reference')->nullable();
            $table->string('site_reference')->nullable();
            $table->string('status', 24)->default('draft');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->decimal('planned_budget', 14, 2)->nullable();
            $table->string('currency', 3)->default('BRL');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'tracking_key']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'channel']);
        });

        Schema::create('campaign_daily_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->date('metric_date');
            $table->decimal('spend', 14, 2)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->decimal('platform_conversions', 14, 2)->default(0);
            $table->string('currency', 3)->default('BRL');
            $table->string('source', 32)->default('manual');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'metric_date', 'source']);
            $table->index(['organization_id', 'metric_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_daily_metrics');
        Schema::dropIfExists('campaigns');
    }
};
