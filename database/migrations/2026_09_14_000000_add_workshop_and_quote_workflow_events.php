<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_orders', function (Blueprint $table): void {
            $table->timestamp('diagnosed_at')->nullable()->after('diagnosis');
            $table->timestamp('authorized_at')->nullable()->after('diagnosed_at');
            $table->text('authorization_note')->nullable()->after('authorized_at');
            $table->timestamp('scheduled_at')->nullable()->after('due_at');
            $table->timestamp('started_at')->nullable()->after('scheduled_at');
        });

        Schema::table('quotes', function (Blueprint $table): void {
            $table->string('sent_via', 32)->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropColumn('sent_via');
        });

        Schema::table('workshop_orders', function (Blueprint $table): void {
            $table->dropColumn(['diagnosed_at', 'authorized_at', 'authorization_note', 'scheduled_at', 'started_at']);
        });
    }
};
