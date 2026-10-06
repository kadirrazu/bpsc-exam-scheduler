<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->unsignedInteger('candidate_count')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('exam_schedules')->whereNull('candidate_count')->exists()) {
            throw new LogicException('Assign candidate counts before reverting the optional-count migration.');
        }
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->unsignedInteger('candidate_count')->nullable(false)->change();
        });
    }
};
