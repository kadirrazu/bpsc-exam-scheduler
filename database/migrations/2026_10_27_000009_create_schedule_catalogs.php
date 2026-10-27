<?php

use Database\Seeders\ExamTypeSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_types', function (Blueprint $t) {
            $t->id();
            $t->string('code', 50)->unique();
            $t->string('name', 100)->unique();
            $t->boolean('is_viva')->default(false);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
        Schema::create('units', function (Blueprint $t) {
            $t->id();
            $t->string('code', 60)->unique();
            $t->string('name', 100)->unique();
            $t->boolean('for_exams')->default(true);
            $t->boolean('for_users')->default(true);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
        Model::withoutEvents(function () {
            (new ExamTypeSeeder)->run();
            (new UnitSeeder)->run();
        });
        $legacyName = function (string $table, string $code): string {
            $name = $code;
            $suffix = 0;
            while (DB::table($table)->where('name', $name)->exists()) {
                $name = 'Legacy '.$code.' '.(++$suffix);
            }

            return $name;
        };
        // Import any historical codes too; do not rewrite schedules, users or their versions.
        foreach (DB::table('exam_schedules')->select('exam_type')->distinct()->pluck('exam_type') as $code) {
            if (DB::table('exam_types')->where('code', $code)->exists()) {
                continue;
            }
            DB::table('exam_types')->insert(['code' => $code, 'name' => $legacyName('exam_types', $code), 'is_viva' => str_contains(strtolower($code), 'viva'), 'sort_order' => 1000, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach (['exam_schedules' => 'for_exams', 'users' => 'for_users'] as $table => $scope) {
            foreach (DB::table($table)->whereNotNull('unit')->where('unit', '!=', '')->select('unit')->distinct()->pluck('unit') as $code) {
                if (! DB::table('units')->where('code', $code)->exists()) {
                    DB::table('units')->insert(['code' => $code, 'name' => $legacyName('units', $code), 'sort_order' => 1000, 'for_exams' => $scope === 'for_exams', 'for_users' => $scope === 'for_users', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
                } else {
                    DB::table('units')->where('code', $code)->update([$scope => true]);
                }
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Catalog rollback is blocked. Restore the matching code and database backup to preserve custom types and units.');
    }
};
