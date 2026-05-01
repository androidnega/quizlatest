<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('quizzes')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('section_order')->default(1);
            $table->timestamps();

            $table->index(['exam_id', 'section_order']);
        });

        foreach (DB::table('quiz_sections')->orderBy('id')->cursor() as $s) {
            DB::table('exam_sections')->insert([
                'id' => $s->id,
                'exam_id' => $s->quiz_id,
                'title' => $s->title,
                'section_order' => $s->section_order,
                'created_at' => $s->created_at,
                'updated_at' => $s->updated_at,
            ]);
        }

        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('section_id')->nullable()->after('quiz_id')->constrained('exam_sections')->cascadeOnDelete();
        });

        DB::table('questions')->whereNotNull('quiz_section_id')->update([
            'section_id' => DB::raw('quiz_section_id'),
        ]);

        $orphanQuizIds = DB::table('questions')
            ->whereNull('section_id')
            ->distinct()
            ->pluck('quiz_id');

        foreach ($orphanQuizIds as $quizId) {
            $maxOrder = (int) DB::table('exam_sections')->where('exam_id', $quizId)->max('section_order');
            $sid = DB::table('exam_sections')->insertGetId([
                'exam_id' => $quizId,
                'title' => 'General',
                'section_order' => $maxOrder + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('questions')
                ->where('quiz_id', $quizId)
                ->whereNull('section_id')
                ->update(['section_id' => $sid]);
        }

        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['quiz_section_id']);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('quiz_section_id');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->unsignedBigInteger('section_id')->nullable(false)->change();
        });

        Schema::drop('quiz_sections');
    }

    public function down(): void
    {
        Schema::create('quiz_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('section_order')->default(1);
            $table->unsignedInteger('question_limit')->nullable();
            $table->timestamps();
            $table->index(['quiz_id', 'section_order']);
        });

        foreach (DB::table('exam_sections')->orderBy('id')->cursor() as $s) {
            DB::table('quiz_sections')->insert([
                'id' => $s->id,
                'quiz_id' => $s->exam_id,
                'title' => $s->title,
                'description' => null,
                'section_order' => $s->section_order,
                'question_limit' => null,
                'created_at' => $s->created_at,
                'updated_at' => $s->updated_at,
            ]);
        }

        Schema::table('questions', function (Blueprint $table) {
            $table->unsignedBigInteger('quiz_section_id')->nullable()->after('quiz_id');
        });

        DB::table('questions')->update([
            'quiz_section_id' => DB::raw('section_id'),
        ]);

        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['section_id']);
            $table->dropColumn('section_id');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->foreign('quiz_section_id')->references('id')->on('quiz_sections')->nullOnDelete();
        });

        Schema::drop('exam_sections');
    }
};
