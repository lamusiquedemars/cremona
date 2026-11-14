<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `'.DB::getTablePrefix().'platform_settings` MODIFY value LONGTEXT NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `'.DB::getTablePrefix().'platform_settings` MODIFY value JSON NOT NULL');
    }
};
