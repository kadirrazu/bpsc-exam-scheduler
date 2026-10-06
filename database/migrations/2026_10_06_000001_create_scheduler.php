<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->unsignedInteger('auth_version')->default(1));
        // Convert legacy staff roles when using a separately copied database.
        DB::table('users')->where('role', 'operator')->update(['role' => 'editor']);
        Schema::create('exam_schedules', function (Blueprint $t) {
            $t->id();
            $t->string('title', 200);
            $t->string('reference', 100)->nullable();
            $t->string('exam_type', 40);
            $t->string('unit', 60);
            $t->date('exam_date');
            $t->time('start_time')->nullable();
            $t->time('end_time')->nullable();
            $t->unsignedInteger('candidate_count');
            $t->unsignedInteger('center_count')->nullable();
            $t->unsignedInteger('board_count')->nullable();
            $t->string('status', 20)->default('scheduled');
            $t->text('notes')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['exam_date', 'start_time'], 'es_date_time');
            $t->index(['unit', 'exam_date'], 'es_unit_date');
            $t->index(['exam_type', 'exam_date'], 'es_type_date');
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('actor_id')->nullable()->index();
            $t->string('actor_name')->nullable();
            $t->string('action', 80)->index();
            $t->string('subject_type', 100)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('channel', 10);
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent', 500)->nullable();
            $t->json('details')->nullable();
            $t->timestamp('created_at')->index();
            $t->index(['subject_type', 'subject_id'], 'al_subject');
        });
        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->id();
            $t->morphs('tokenable');
            $t->text('name');
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable()->index();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('exam_schedules');
        DB::table('users')->where('role', 'editor')->update(['role' => 'operator']);
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('auth_version'));
    }
};
