<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Department;
use App\Models\ExaminerCourseAssignment;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Course::class);

        return view('coordinator.courses.index', [
            'courses' => Course::query()
                ->whereIn('department_id', $this->departmentIds())
                ->with('department')
                ->orderBy('title')
                ->paginate(15),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('viewAny', Course::class);

        $departmentIds = $this->departmentIds();
        $selectedDepartmentId = (int) $request->integer('department_id', $departmentIds[0] ?? 0);
        $this->authorize('update', Course::make([
            'department_id' => $selectedDepartmentId,
            'university_id' => auth()->user()->university_id,
        ]));

        return view('coordinator.courses.create', [
            'department' => Department::query()->find($selectedDepartmentId),
            'departmentId' => $selectedDepartmentId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Course::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100'],
            'department_id' => ['required', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $departmentId = (int) $validated['department_id'];
        $this->authorize('update', Course::make([
            'department_id' => $departmentId,
            'university_id' => auth()->user()->university_id,
        ]));

        $request->validate([
            'code' => [
                Rule::unique('courses', 'code')
                    ->where(fn ($query) => $query
                        ->where('university_id', auth()->user()->university_id)
                        ->where('department_id', $departmentId)),
            ],
        ]);

        Course::create([
            'university_id' => auth()->user()->university_id,
            'department_id' => $departmentId,
            'title' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'credit_hours' => null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('coordinator.courses.index')->with('status', 'Course created successfully.');
    }

    public function edit(Course $course): View
    {
        $this->authorize('update', $course);

        return view('coordinator.courses.edit', [
            'course' => $course->load('department'),
        ]);
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('update', $course);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $request->validate([
            'code' => [
                Rule::unique('courses', 'code')
                    ->where(fn ($query) => $query
                        ->where('university_id', auth()->user()->university_id)
                        ->where('department_id', $course->department_id))
                    ->ignore($course->id),
            ],
        ]);

        $course->update([
            'title' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('coordinator.courses.index')->with('status', 'Course updated successfully.');
    }

    public function toggleStatus(Course $course): RedirectResponse
    {
        $this->authorize('update', $course);

        $course->update(['is_active' => ! $course->is_active]);

        return redirect()->route('coordinator.courses.index')->with('status', 'Course status updated.');
    }

    public function editExaminerAssignments(Request $request): View
    {
        $this->authorize('viewAny', Course::class);

        $courses = Course::query()
            ->whereIn('department_id', $this->departmentIds())
            ->orderBy('title')
            ->get(['id', 'title', 'code', 'department_id']);

        $selectedCourseId = (int) $request->integer('course_id');
        $selectedCourse = $selectedCourseId > 0
            ? $courses->firstWhere('id', $selectedCourseId)
            : null;

        $examiners = User::query()
            ->where('role', 'examiner')
            ->where('university_id', auth()->user()->university_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $assignedExaminerIds = [];
        if ($selectedCourse !== null) {
            $this->authorize('update', $selectedCourse);
            $assignedExaminerIds = ExaminerCourseAssignment::query()
                ->where('course_id', $selectedCourse->id)
                ->where('is_active', true)
                ->pluck('examiner_user_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return view('coordinator.courses.assign-examiners', [
            'courses' => $courses,
            'selectedCourse' => $selectedCourse,
            'examiners' => $examiners,
            'assignedExaminerIds' => $assignedExaminerIds,
        ]);
    }

    public function updateExaminerAssignments(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Course::class);

        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'examiner_ids' => ['nullable', 'array'],
            'examiner_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $course = Course::query()->findOrFail((int) $validated['course_id']);
        $this->authorize('update', $course);

        $examinerIds = collect($validated['examiner_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $allowedExaminerIds = User::query()
            ->where('role', 'examiner')
            ->where('university_id', auth()->user()->university_id)
            ->whereIn('id', $examinerIds->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        abort_unless(count($allowedExaminerIds) === $examinerIds->count(), 422);

        DB::transaction(function () use ($course, $allowedExaminerIds): void {
            ExaminerCourseAssignment::query()
                ->where('course_id', $course->id)
                ->delete();

            foreach ($allowedExaminerIds as $examinerId) {
                ExaminerCourseAssignment::query()->create([
                    'course_id' => $course->id,
                    'examiner_user_id' => $examinerId,
                    'assigned_by' => auth()->id(),
                    'is_active' => true,
                    'permissions' => null,
                    'starts_at' => null,
                    'ends_at' => null,
                ]);
            }
        });

        return redirect()
            ->route('coordinator.courses.examiners.edit', ['course_id' => $course->id])
            ->with('status', __('Examiner assignments updated.'));
    }

    private function departmentIds(): array
    {
        return auth()->user()
            ->coordinatorAssignments()
            ->where('is_active', true)
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
