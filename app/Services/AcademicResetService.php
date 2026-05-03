<?php

namespace App\Services;

use App\Models\AcademicResetSnapshot;
use App\Models\ActivityLog;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Level;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class AcademicResetService
{
    public const TYPE_COMPLETE = 'complete';

    public const TYPE_PARTIAL = 'partial';

    public const TYPE_PEACE = 'peace';

    public const TYPE_CONTINUAL = 'continual';

    /**
     * @param  array{
     *     program_ids?: list<int>,
     *     level_ids?: list<int>,
     *     class_ids?: list<int>,
     *     promote_class_rows?: bool,
     * }  $filters
     * @return array{
     *     frozen_class_ids: list<int>,
     *     frozen_student_ids: list<int>,
     *     frozen_program_ids: list<int>,
     *     frozen_level_ids: list<int>,
     *     narrative: list<string>,
     * }
     */
    public function computeFrozenSets(User $coordinator, int $departmentId, string $resetType, array $filters): array
    {
        $this->assertOwnsDepartment($coordinator, $departmentId);

        $programIdsInDept = Program::query()
            ->where('department_id', $departmentId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return match ($resetType) {
            self::TYPE_COMPLETE => $this->freezeComplete($programIdsInDept),
            self::TYPE_PARTIAL => $this->freezePartial($programIdsInDept, $filters),
            self::TYPE_PEACE => $this->freezePeace($programIdsInDept),
            self::TYPE_CONTINUAL => $this->freezeContinualStudents($programIdsInDept, $filters),
            default => throw new \InvalidArgumentException('Invalid reset type.'),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function applySnapshot(AcademicResetSnapshot $snapshot, User $coordinator): void
    {
        $this->assertOwnsDepartment($coordinator, (int) $snapshot->department_id);

        if ($snapshot->isApplied()) {
            throw new \RuntimeException('This reset has already been applied.');
        }

        $payload = $snapshot->payload;
        $resetType = (string) ($payload['reset_type'] ?? $snapshot->reset_type);
        $frozenClassIds = array_map('intval', $payload['frozen_class_ids'] ?? []);
        $frozenStudentIds = array_map('intval', $payload['frozen_student_ids'] ?? []);
        $universityId = (int) ($payload['university_id'] ?? $coordinator->university_id);

        DB::transaction(function () use ($snapshot, $coordinator, $resetType, $frozenClassIds, $frozenStudentIds, $payload, $universityId): void {

            match ($resetType) {
                self::TYPE_COMPLETE => $this->runComplete($frozenClassIds, $frozenStudentIds),
                self::TYPE_PARTIAL => $this->runPartial($frozenClassIds, $frozenStudentIds),
                self::TYPE_PEACE => $this->runPeace($frozenClassIds),
                self::TYPE_CONTINUAL => $this->runContinual(
                    $frozenStudentIds,
                    $frozenClassIds,
                    (bool) ($payload['promote_class_rows'] ?? false),
                    $universityId,
                ),
                default => throw new \RuntimeException('Unknown reset type in snapshot.'),
            };

            $outcome = [
                'applied_at' => now()->toISOString(),
                'classes_touched' => count($frozenClassIds),
                'students_touched' => count($frozenStudentIds),
            ];

            $snapshot->update([
                'applied_at' => now(),
                'summary' => array_merge($snapshot->summary ?? [], ['outcome' => $outcome]),
            ]);

            ActivityLog::create([
                'user_id' => $coordinator->id,
                'quiz_id' => null,
                'event_type' => 'academic_reset_applied',
                'event_data' => [
                    'snapshot_id' => $snapshot->id,
                    'department_id' => (int) $snapshot->department_id,
                    'reset_type' => $resetType,
                    'summary' => $snapshot->summary,
                ],
                'created_at' => now(),
            ]);
        });
    }

    private function assertOwnsDepartment(User $user, int $departmentId): void
    {
        $allowed = $user->coordinatorAssignments()
            ->where('is_active', true)
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->contains($departmentId);

        abort_unless($allowed && $user->role === 'coordinator', 403);
    }

    /**
     * @param  list<int>  $programIdsInDept
     * @return array{frozen_class_ids: list<int>, frozen_student_ids: list<int>, frozen_program_ids: list<int>, frozen_level_ids: list<int>, narrative: list<string>}
     */
    private function freezeComplete(array $programIdsInDept): array
    {
        $classIds = Classroom::query()
            ->whereIn('program_id', $programIdsInDept)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $studentIds = User::query()
            ->where('role', 'student')
            ->whereIn('program_id', $programIdsInDept)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return [
            'frozen_class_ids' => $classIds,
            'frozen_student_ids' => $studentIds,
            'frozen_program_ids' => $programIdsInDept,
            'frozen_level_ids' => [],
            'narrative' => [
                'Deactivate all classes in this department\'s programs.',
                'Clear class assignment for all students in those programs (students are not deleted).',
                'Exams, results, sessions, and logs are not modified.',
            ],
        ];
    }

    /**
     * @param  list<int>  $programIdsInDept
     * @param  array{program_ids?: list<int>, level_ids?: list<int>, class_ids?: list<int>}  $filters
     */
    private function freezePartial(array $programIdsInDept, array $filters): array
    {
        $classIdsFilter = array_values(array_unique(array_map('intval', $filters['class_ids'] ?? [])));
        $rawProgramFilter = array_values(array_unique(array_map('intval', $filters['program_ids'] ?? [])));
        $levelFilter = array_values(array_unique(array_map('intval', $filters['level_ids'] ?? [])));

        abort_if(
            $classIdsFilter === [] && $rawProgramFilter === [] && $levelFilter === [],
            422,
            'Partial reset requires at least one program, level, or class filter.',
        );

        $programFilter = array_values(array_intersect($rawProgramFilter, $programIdsInDept));
        abort_if(
            $rawProgramFilter !== [] && $programFilter === [],
            422,
            'No selected programs belong to this department.',
        );

        if ($classIdsFilter !== []) {
            $validClassIds = Classroom::query()
                ->whereIn('id', $classIdsFilter)
                ->whereIn('program_id', $programIdsInDept)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();
            $sortedRequested = $classIdsFilter;
            sort($sortedRequested);
            abort_unless(
                $validClassIds === array_values($sortedRequested),
                422,
                'One or more classes are invalid or outside your department scope.',
            );
        }

        $query = Classroom::query()->whereIn('program_id', $programIdsInDept);

        if ($classIdsFilter !== []) {
            $query->whereIn('id', $classIdsFilter);
        } else {
            if ($programFilter !== []) {
                $query->whereIn('program_id', $programFilter);
            }
            if ($levelFilter !== []) {
                $query->whereIn('level_id', $levelFilter);
            }
        }

        $classIds = $query->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        abort_if($classIds === [] && ($classIdsFilter !== [] || $programFilter !== [] || $levelFilter !== []), 422, 'No classes matched your filters.');

        abort_if($classIds === [], 422, 'Partial reset requires at least one matching class (choose filters or specific classes).');

        $studentIds = User::query()
            ->where('role', 'student')
            ->whereIn('class_id', $classIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $programsTouched = Classroom::query()->whereIn('id', $classIds)->distinct()->pluck('program_id')->map(fn ($id) => (int) $id)->all();
        $levelsTouched = Classroom::query()->whereIn('id', $classIds)->distinct()->pluck('level_id')->map(fn ($id) => (int) $id)->all();

        return [
            'frozen_class_ids' => $classIds,
            'frozen_student_ids' => $studentIds,
            'frozen_program_ids' => $programsTouched,
            'frozen_level_ids' => $levelsTouched,
            'narrative' => [
                'Deactivate only the selected classes.',
                'Students linked to those classes will have their class assignment cleared.',
            ],
        ];
    }

    /**
     * @param  list<int>  $programIdsInDept
     */
    private function freezePeace(array $programIdsInDept): array
    {
        $currentYear = (int) Carbon::now()->year;

        $candidates = Classroom::query()
            ->whereIn('program_id', $programIdsInDept)
            ->whereNotNull('academic_year')
            ->get(['id', 'academic_year']);

        $classIds = [];
        foreach ($candidates as $row) {
            $ay = (string) $row->academic_year;
            if (preg_match('/(\d{4})/', $ay, $m)) {
                $startYear = (int) $m[1];
                if ($startYear < $currentYear) {
                    $classIds[] = (int) $row->id;
                }
            }
        }

        return [
            'frozen_class_ids' => array_values(array_unique($classIds)),
            'frozen_student_ids' => [],
            'frozen_program_ids' => [],
            'frozen_level_ids' => [],
            'narrative' => [
                'Soft cleanup: deactivate classes whose academic year starts before the current calendar year.',
                'Student class assignments are preserved.',
                'No exam data is removed.',
            ],
        ];
    }

    /**
     * @param  list<int>  $programIdsInDept
     * @param  array{program_ids?: list<int>, level_ids?: list<int>, class_ids?: list<int>}  $filters
     */
    private function freezeContinualStudents(array $programIdsInDept, array $filters): array
    {
        $classIdsFilter = array_map('intval', $filters['class_ids'] ?? []);
        $programFilter = array_values(array_intersect(array_map('intval', $filters['program_ids'] ?? []), $programIdsInDept));
        $levelFilter = array_map('intval', $filters['level_ids'] ?? []);

        $q = User::query()->where('role', 'student')->whereIn('program_id', $programIdsInDept);

        if ($classIdsFilter !== []) {
            $q->whereIn('class_id', $classIdsFilter);
        } else {
            if ($programFilter !== []) {
                $q->whereIn('program_id', $programFilter);
            }
            if ($levelFilter !== []) {
                $q->whereIn('level_id', $levelFilter);
            }
        }

        $studentIds = $q->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        $classScope = Classroom::query()->whereIn('program_id', $programIdsInDept);
        if ($classIdsFilter !== []) {
            $classScope->whereIn('id', $classIdsFilter);
        } else {
            if ($programFilter !== []) {
                $classScope->whereIn('program_id', $programFilter);
            }
            if ($levelFilter !== []) {
                $classScope->whereIn('level_id', $levelFilter);
            }
        }

        $classIds = $classScope->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        return [
            'frozen_class_ids' => $classIds,
            'frozen_student_ids' => $studentIds,
            'frozen_program_ids' => $programFilter !== [] ? $programFilter : $programIdsInDept,
            'frozen_level_ids' => $levelFilter,
            'narrative' => [
                'Advance students to the next level by sort order; clear class assignments.',
                'Students already on the final level are marked inactive (alumni handoff).',
                'Optional: promote class rows when unique constraints allow.',
            ],
        ];
    }

    /**
     * @param  list<int>  $frozenClassIds
     * @param  list<int>  $frozenStudentIds
     */
    private function runComplete(array $frozenClassIds, array $frozenStudentIds): void
    {
        if ($frozenClassIds !== []) {
            Classroom::query()->whereIn('id', $frozenClassIds)->update(['is_active' => false]);
        }
        if ($frozenStudentIds !== []) {
            User::query()->whereIn('id', $frozenStudentIds)->update(['class_id' => null]);
        }
    }

    /**
     * @param  list<int>  $frozenClassIds
     * @param  list<int>  $frozenStudentIds
     */
    private function runPartial(array $frozenClassIds, array $frozenStudentIds): void
    {
        if ($frozenClassIds !== []) {
            Classroom::query()->whereIn('id', $frozenClassIds)->update(['is_active' => false]);
        }
        if ($frozenStudentIds !== []) {
            User::query()->whereIn('id', $frozenStudentIds)->whereIn('class_id', $frozenClassIds)->update(['class_id' => null]);
        }
    }

    /**
     * @param  list<int>  $frozenClassIds
     */
    private function runPeace(array $frozenClassIds): void
    {
        if ($frozenClassIds !== []) {
            Classroom::query()->whereIn('id', $frozenClassIds)->update(['is_active' => false]);
        }
    }

    /**
     * @param  list<int>  $frozenStudentIds
     * @param  list<int>  $frozenClassIds
     */
    private function runContinual(array $frozenStudentIds, array $frozenClassIds, bool $promoteClassRows, int $universityId): void
    {
        $students = User::query()
            ->whereIn('id', $frozenStudentIds)
            ->where('role', 'student')
            ->get();

        foreach ($students as $student) {
            $levelId = $student->level_id;
            if ($levelId === null) {
                continue;
            }
            $current = Level::query()->where('id', $levelId)->where('university_id', $universityId)->first();
            if ($current === null) {
                continue;
            }
            $next = Level::query()
                ->where('university_id', $universityId)
                ->where('sort_order', '>', $current->sort_order)
                ->orderBy('sort_order')
                ->first();

            if ($next !== null) {
                $student->update([
                    'level_id' => $next->id,
                    'class_id' => null,
                ]);
            } else {
                $student->update([
                    'class_id' => null,
                    'is_active' => false,
                ]);
            }
        }

        if ($promoteClassRows && $frozenClassIds !== []) {
            foreach ($frozenClassIds as $cid) {
                $class = Classroom::query()->find($cid);
                if ($class === null) {
                    continue;
                }
                $current = Level::query()->where('id', $class->level_id)->where('university_id', $universityId)->first();
                if ($current === null) {
                    continue;
                }
                $next = Level::query()
                    ->where('university_id', $universityId)
                    ->where('sort_order', '>', $current->sort_order)
                    ->orderBy('sort_order')
                    ->first();
                if ($next === null) {
                    Classroom::query()->whereKey($class->id)->update(['is_active' => false]);

                    continue;
                }
                $collision = Classroom::query()
                    ->where('program_id', $class->program_id)
                    ->where('level_id', $next->id)
                    ->where('name', $class->name)
                    ->where('section', $class->section)
                    ->whereKeyNot($class->id)
                    ->exists();
                if (! $collision) {
                    Classroom::query()->whereKey($class->id)->update(['level_id' => $next->id]);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public static function validateDepartmentExists(int $departmentId): Department
    {
        $d = Department::query()->find($departmentId);
        abort_if($d === null, 404);

        return $d;
    }
}
