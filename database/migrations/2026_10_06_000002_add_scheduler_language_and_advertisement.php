<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_schedules', function (Blueprint $t) {
            $t->string('advertisement_number', 100)->nullable();
            $t->unsignedSmallInteger('advertisement_year')->nullable();
        });
        Schema::table('users', fn (Blueprint $t) => $t->string('preferred_locale', 2)->default('bn'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('preferred_locale'));
        Schema::table('exam_schedules', fn (Blueprint $t) => $t->dropColumn(['advertisement_number', 'advertisement_year']));
    }
};
