<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->unsignedInteger('vacant_posts')->nullable();
            $table->json('board_structure')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('exam_schedules', fn (Blueprint $table) => $table->dropColumn(['vacant_posts', 'board_structure']));
    }
};
