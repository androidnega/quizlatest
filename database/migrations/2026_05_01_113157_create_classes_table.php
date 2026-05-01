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
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('university_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('level_id');
            $table->string('name');
            $table->string('section')->nullable();
            $table->string('academic_year')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['program_id', 'level_id', 'name', 'section']);
            $table->index(['university_id', 'program_id', 'level_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
