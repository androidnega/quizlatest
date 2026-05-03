<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_reset_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            $table->string('reset_type', 32);
            $table->json('payload');
            $table->json('summary')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'created_at']);
            $table->index(['initiated_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_reset_snapshots');
    }
};
