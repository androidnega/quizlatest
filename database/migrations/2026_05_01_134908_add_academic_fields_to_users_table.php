<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('university_id')->constrained()->nullOnDelete();
            $table->foreignId('level_id')->nullable()->after('program_id')->constrained()->nullOnDelete();
            $table->foreignId('class_id')->nullable()->after('level_id')->constrained('classes')->nullOnDelete();

            $table->index(['role', 'program_id', 'level_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['program_id']);
            $table->dropForeign(['level_id']);
            $table->dropForeign(['class_id']);
            $table->dropIndex(['role', 'program_id', 'level_id']);
            $table->dropColumn(['program_id', 'level_id', 'class_id']);
        });
    }
};
