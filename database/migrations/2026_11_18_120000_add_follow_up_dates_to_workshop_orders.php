<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_orders', function (Blueprint $table): void {
            $table->date('examination_on')->nullable();
            $table->date('quote_follow_up_on')->nullable();
            $table->date('work_due_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('workshop_orders', function (Blueprint $table): void {
            $table->dropColumn(['examination_on', 'quote_follow_up_on', 'work_due_on']);
        });
    }
};
