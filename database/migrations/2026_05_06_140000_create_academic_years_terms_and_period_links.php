<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('university_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 24)->default('upcoming');
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index(['university_id', 'is_active']);
            $table->index(['university_id', 'status']);
        });

        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 24)->default('upcoming');
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index(['academic_year_id', 'is_active']);
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('academic_year')->constrained('academic_years')->nullOnDelete();
        });

        Schema::table('class_course', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('course_id')->constrained('academic_years')->nullOnDelete();
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('university_id')->constrained('academic_years')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->after('academic_year_id')->constrained('terms')->nullOnDelete();
        });

        Schema::table('results', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('quiz_id')->constrained('academic_years')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->after('academic_year_id')->constrained('terms')->nullOnDelete();
        });

        Schema::table('academic_reset_snapshots', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('department_id')->constrained('academic_years')->nullOnDelete();
        });

        $this->seedLegacyPeriodLinks();
    }

    /**
     * Backfill academic years from existing class labels and attach FKs without deleting data.
     */
    private function seedLegacyPeriodLinks(): void
    {
        $now = Carbon::now();
        $uniIds = DB::table('universities')->pluck('id');

        foreach ($uniIds as $uniId) {
            $uniId = (int) $uniId;
            $labels = DB::table('classes')
                ->where('university_id', $uniId)
                ->whereNotNull('academic_year')
                ->where('academic_year', '!=', '')
                ->distinct()
                ->pluck('academic_year');

            $labelToYearId = [];

            if ($labels->isEmpty()) {
                $startYear = (int) $now->year;
                $start = Carbon::create($startYear, 9, 1)->toDateString();
                $end = Carbon::create($startYear + 1, 8, 31)->toDateString();
                $name = $startYear.'/'.($startYear + 1);
                $yearId = DB::table('academic_years')->insertGetId([
                    'university_id' => $uniId,
                    'name' => $name,
                    'start_date' => $start,
                    'end_date' => $end,
                    'status' => 'active',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $labelToYearId['__default__'] = $yearId;
                $this->insertFullYearTerm($yearId, $name, $start, $end, $now);
            } else {
                $createdIds = [];
                foreach ($labels as $label) {
                    $label = (string) $label;
                    [$start, $end] = $this->inferYearBoundsFromLabel($label, $now);
                    $yearId = DB::table('academic_years')->insertGetId([
                        'university_id' => $uniId,
                        'name' => $label,
                        'start_date' => $start,
                        'end_date' => $end,
                        'status' => 'closed',
                        'is_active' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $labelToYearId[$label] = $yearId;
                    $createdIds[] = ['id' => $yearId, 'start' => $start, 'end' => $end, 'name' => $label];
                    $this->insertFullYearTerm($yearId, $label.' — Full year', $start, $end, $now);
                }

                $activeId = null;
                foreach ($createdIds as $row) {
                    $s = Carbon::parse($row['start']);
                    $e = Carbon::parse($row['end']);
                    if ($now->betweenIncluded($s, $e)) {
                        $activeId = $row['id'];

                        break;
                    }
                }
                if ($activeId === null && $createdIds !== []) {
                    usort($createdIds, fn ($a, $b) => strcmp($b['start'], $a['start']));
                    $activeId = $createdIds[0]['id'];
                }
                if ($activeId !== null) {
                    DB::table('academic_years')->where('university_id', $uniId)->update(['is_active' => false, 'status' => 'closed']);
                    DB::table('academic_years')->where('id', $activeId)->update(['is_active' => true, 'status' => 'active']);
                }
            }

            $defaultYearId = $labelToYearId['__default__'] ?? null;

            foreach ($labelToYearId as $lbl => $yid) {
                if ($lbl === '__default__') {
                    continue;
                }
                DB::table('classes')
                    ->where('university_id', $uniId)
                    ->where('academic_year', $lbl)
                    ->update(['academic_year_id' => $yid]);
            }

            if ($defaultYearId !== null) {
                DB::table('classes')
                    ->where('university_id', $uniId)
                    ->whereNull('academic_year_id')
                    ->update(['academic_year_id' => $defaultYearId]);
            } else {
                $activeYearId = DB::table('academic_years')
                    ->where('university_id', $uniId)
                    ->where('is_active', true)
                    ->value('id');
                if ($activeYearId !== null) {
                    DB::table('classes')
                        ->where('university_id', $uniId)
                        ->whereNull('academic_year_id')
                        ->update(['academic_year_id' => $activeYearId]);
                }
            }

            DB::statement('UPDATE class_course SET academic_year_id = (
                SELECT classes.academic_year_id FROM classes WHERE classes.id = class_course.class_id
            ) WHERE EXISTS (SELECT 1 FROM classes WHERE classes.id = class_course.class_id)');

            $activeForUni = DB::table('academic_years')
                ->where('university_id', $uniId)
                ->where('is_active', true)
                ->value('id');

            if ($activeForUni !== null) {
                DB::table('quizzes')
                    ->where('university_id', $uniId)
                    ->whereNull('academic_year_id')
                    ->update(['academic_year_id' => $activeForUni]);

                $activeTermId = DB::table('terms')
                    ->where('academic_year_id', $activeForUni)
                    ->where('is_active', true)
                    ->value('id');

                if ($activeTermId !== null) {
                    DB::table('quizzes')
                        ->where('university_id', $uniId)
                        ->whereNull('term_id')
                        ->update(['term_id' => $activeTermId]);
                }
            }

            DB::statement('UPDATE results SET academic_year_id = (
                SELECT quizzes.academic_year_id FROM quizzes WHERE quizzes.id = results.quiz_id
            ), term_id = (
                SELECT quizzes.term_id FROM quizzes WHERE quizzes.id = results.quiz_id
            ) WHERE EXISTS (SELECT 1 FROM quizzes WHERE quizzes.id = results.quiz_id)');
        }
    }

    /**
     * @return array{0: string, 1: string} date strings Y-m-d
     */
    private function inferYearBoundsFromLabel(string $label, Carbon $now): array
    {
        if (preg_match('/(\d{4})/', $label, $m)) {
            $y = (int) $m[1];
            $start = Carbon::create($y, 9, 1)->toDateString();
            $end = Carbon::create($y + 1, 8, 31)->toDateString();

            return [$start, $end];
        }

        $y = (int) $now->year;
        $start = Carbon::create($y, 9, 1)->toDateString();
        $end = Carbon::create($y + 1, 8, 31)->toDateString();

        return [$start, $end];
    }

    private function insertFullYearTerm(int $academicYearId, string $name, string $start, string $end, Carbon $now): void
    {
        DB::table('terms')->insert([
            'academic_year_id' => $academicYearId,
            'name' => $name,
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'active',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::table('academic_reset_snapshots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_year_id');
        });

        Schema::table('results', function (Blueprint $table) {
            $table->dropConstrainedForeignId('term_id');
            $table->dropConstrainedForeignId('academic_year_id');
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('term_id');
            $table->dropConstrainedForeignId('academic_year_id');
        });

        Schema::table('class_course', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_year_id');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_year_id');
        });

        Schema::dropIfExists('terms');
        Schema::dropIfExists('academic_years');
    }
};
