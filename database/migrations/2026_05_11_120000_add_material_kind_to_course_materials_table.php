<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_materials', function (Blueprint $table) {
            $table->string('material_kind', 32)->default('supplementary')->after('title');
            $table->index(['course_id', 'material_kind', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('course_materials', function (Blueprint $table) {
            $table->dropIndex(['course_id', 'material_kind', 'status']);
            $table->dropColumn('material_kind');
        });
    }
};
