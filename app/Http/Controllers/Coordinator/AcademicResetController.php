<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\AcademicResetSnapshot;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Level;
use App\Models\Program;
use App\Models\User;
use App\Services\AcademicResetService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AcademicResetController extends Controller
{
    public function __construct(
        private readonly AcademicResetService $academicResetService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'coordinator', 403);

        $departments = Department::query()
            ->whereIn('id', $this->departmentIds($user))
            ->orderBy('name')
            ->get();

        $departmentId = (int) $request->integer('department_id') ?: $departments->first()?->id;

        $programs = collect();
        $levels = collect();
        $classes = collect();

        if ($departmentId !== 0 && $this->ownsDepartment($user, $departmentId)) {
            $programs = Program::query()
                ->where('department_id', $departmentId)
                ->orderBy('name')
                ->get();

            $levels = Level::query()
                ->where('university_id', $user->university_id)
                ->orderBy('sort_order')
                ->get();

            $classes = Classroom::query()
                ->whereIn('program_id', $programs->pluck('id'))
                ->with(['program', 'level'])
                ->orderBy('name')
                ->get();
        }

        return view('coordinator.academic-reset.index', [
            'departments' => $departments,
            'departmentId' => $departmentId,
            'programs' => $programs,
            'levels' => $levels,
            'classes' => $classes,
            'resetTypes' => [
                AcademicResetService::TYPE_COMPLETE => 'Complete reset — deactivate all classes in department programs; clear student class assignments.',
                AcademicResetService::TYPE_PARTIAL => 'Partial reset — chosen programs / levels / classes only.',
                AcademicResetService::TYPE_PEACE => 'Peace reset — deactivate classes with academic year before current calendar year only.',
                AcademicResetService::TYPE_CONTINUAL => 'Continual reset — promote students to next level; final level becomes inactive.',
            ],
        ]);
    }

    public function preview(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'coordinator', 403);

        $validated = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'reset_type' => ['required', 'string', 'in:complete,partial,peace,continual'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['integer'],
            'level_ids' => ['nullable', 'array'],
            'level_ids.*' => ['integer'],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['integer'],
            'promote_class_rows' => ['nullable', 'boolean'],
        ]);

        $departmentId = (int) $validated['department_id'];
        $this->authorizeDepartment($user, $departmentId);

        $department = AcademicResetService::validateDepartmentExists($departmentId);

        $filters = [
            'program_ids' => array_map('intval', $validated['program_ids'] ?? []),
            'level_ids' => array_map('intval', $validated['level_ids'] ?? []),
            'class_ids' => array_map('intval', $validated['class_ids'] ?? []),
            'promote_class_rows' => $request->boolean('promote_class_rows'),
        ];

        $validProgramIds = Program::query()
            ->where('department_id', $departmentId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($filters['program_ids'] as $pid) {
            abort_unless(in_array($pid, $validProgramIds, true), 422, 'Invalid program selection.');
        }

        $validLevelIds = Level::query()
            ->where('university_id', $user->university_id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($filters['level_ids'] as $lid) {
            abort_unless(in_array($lid, $validLevelIds, true), 422, 'Invalid level selection.');
        }

        if (($filters['class_ids'] ?? []) !== []) {
            $allowedClassIds = Classroom::query()
                ->whereIn('program_id', $validProgramIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            foreach ($filters['class_ids'] as $cid) {
                abort_unless(in_array($cid, $allowedClassIds, true), 422, 'Invalid class selection.');
            }
        }

        $frozen = $this->academicResetService->computeFrozenSets(
            $user,
            $departmentId,
            $validated['reset_type'],
            $filters,
        );

        $payload = [
            'reset_type' => $validated['reset_type'],
            'filters' => [
                'program_ids' => $filters['program_ids'],
                'level_ids' => $filters['level_ids'],
                'class_ids' => $filters['class_ids'],
                'promote_class_rows' => $filters['promote_class_rows'],
            ],
            'frozen_class_ids' => $frozen['frozen_class_ids'],
            'frozen_student_ids' => $frozen['frozen_student_ids'],
            'frozen_program_ids' => $frozen['frozen_program_ids'],
            'frozen_level_ids' => $frozen['frozen_level_ids'],
            'university_id' => (int) $department->university_id,
            'promote_class_rows' => $filters['promote_class_rows'],
        ];

        $summary = [
            'department_id' => $departmentId,
            'department_name' => $department->name,
            'class_count' => count($frozen['frozen_class_ids']),
            'student_count' => count($frozen['frozen_student_ids']),
            'program_count' => count(array_unique($frozen['frozen_program_ids'])),
            'level_count' => count(array_unique($frozen['frozen_level_ids'])),
            'narrative' => $frozen['narrative'],
            'previewed_at' => now()->toISOString(),
        ];

        $snapshot = AcademicResetSnapshot::query()->create([
            'department_id' => $departmentId,
            'initiated_by' => $user->id,
            'reset_type' => $validated['reset_type'],
            'payload' => $payload,
            'summary' => $summary,
            'applied_at' => null,
        ]);

        return redirect()
            ->route('coordinator.academic-reset.review', $snapshot)
            ->with('status', 'Review the preview below, then confirm to apply.');
    }

    public function review(Request $request, AcademicResetSnapshot $snapshot): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'coordinator', 403);
        $this->authorizeSnapshot($user, $snapshot);

        if ($snapshot->isApplied()) {
            return redirect()
                ->route('coordinator.academic-reset.index')
                ->with('status', 'This reset snapshot was already applied.');
        }

        return view('coordinator.academic-reset.review', [
            'snapshot' => $snapshot->load(['department', 'initiator']),
        ]);
    }

    public function apply(Request $request, AcademicResetSnapshot $snapshot): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'coordinator', 403);
        $this->authorizeSnapshot($user, $snapshot);

        $validated = $request->validate([
            'confirmation_phrase' => ['required', 'string', 'in:RESET'],
            'confirm_understood' => ['accepted'],
        ]);

        abort_if($snapshot->isApplied(), 422, 'This reset was already applied.');

        $this->academicResetService->applySnapshot($snapshot, $user);

        return redirect()
            ->route('coordinator.academic-reset.index')
            ->with('status', 'Academic reset applied successfully. An activity log entry was recorded.');
    }

    /**
     * @return list<int>
     */
    private function departmentIds(User $user): array
    {
        return $user->coordinatorAssignments()
            ->where('is_active', true)
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function ownsDepartment(User $user, int $departmentId): bool
    {
        return in_array($departmentId, $this->departmentIds($user), true);
    }

    private function authorizeDepartment(User $user, int $departmentId): void
    {
        abort_unless($this->ownsDepartment($user, $departmentId), 403);
    }

    private function authorizeSnapshot(User $user, AcademicResetSnapshot $snapshot): void
    {
        $this->authorizeDepartment($user, (int) $snapshot->department_id);
        abort_unless((int) $snapshot->initiated_by === (int) $user->id, 403);
    }
}
