<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('status');
            $table->timestamp('start_time')->nullable()->after('published_at');
            $table->timestamp('end_time')->nullable()->after('start_time');
        });

        if (Schema::hasColumn('quizzes', 'available_from')) {
            DB::table('quizzes')->select(['id', 'available_from', 'available_to'])->orderBy('id')->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('quizzes')->where('id', $row->id)->update([
                        'start_time' => $row->available_from,
                        'end_time' => $row->available_to,
                    ]);
                }
            });

            Schema::table('quizzes', function (Blueprint $table) {
                $table->dropColumn(['available_from', 'available_to']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_to')->nullable();
        });

        DB::table('quizzes')->select(['id', 'start_time', 'end_time'])->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('quizzes')->where('id', $row->id)->update([
                    'available_from' => $row->start_time,
                    'available_to' => $row->end_time,
                ]);
            }
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn(['published_at', 'start_time', 'end_time']);
        });
    }
};
