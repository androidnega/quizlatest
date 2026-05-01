<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Department;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        abort_unless(in_array($selectedDepartmentId, $departmentIds, true), 403);

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
        abort_unless(in_array($departmentId, $this->departmentIds(), true), 403);

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
