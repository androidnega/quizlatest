<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('pool_status', 16)->default('approved')->after('metadata');
        });

        DB::table('questions')->update(['pool_status' => 'approved']);

        Schema::table('quizzes', function (Blueprint $table) {
            $table->unsignedInteger('questions_per_student')->nullable()->after('total_marks');
            $table->boolean('randomize_questions')->default(false)->after('questions_per_student');
            $table->boolean('randomize_options')->default(false)->after('randomize_questions');
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn(['questions_per_student', 'randomize_questions', 'randomize_options']);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('pool_status');
        });
    }
};
