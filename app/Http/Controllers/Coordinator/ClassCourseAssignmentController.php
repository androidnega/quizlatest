<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Course;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClassCourseAssignmentController extends Controller
{
    public function edit(Request $request): View
    {
        $this->authorize('viewAny', Classroom::class);

        $classes = $this->scopedClasses();
        $courses = $this->scopedCourses();
        $selectedClassId = (int) $request->integer('class_id', $classes->first()?->id ?? 0);
        $selectedClass = $classes->firstWhere('id', $selectedClassId);

        if ($selectedClass) {
            $this->authorize('view', $selectedClass);
        } elseif ($classes->isNotEmpty()) {
            abort(403);
        }

        $assignedCourseIds = $selectedClass
            ? DB::table('class_course')
                ->where('class_id', $selectedClass->id)
                ->pluck('course_id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        $availableCourses = $selectedClass
            ? $courses->where('department_id', $selectedClass->program?->department_id)->values()
            : collect();

        return view('coordinator.courses.assign', [
            'classes' => $classes,
            'courses' => $availableCourses,
            'selectedClass' => $selectedClass,
            'assignedCourseIds' => $assignedCourseIds,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
        ]);

        $classroom = Classroom::query()->find((int) $validated['class_id']);
        abort_if($classroom === null, 404);
        $this->authorize('assignCourses', $classroom);

        $selectedCourseIds = collect($validated['course_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
        $scopedCourses = $this->scopedCourses()->whereIn('id', $selectedCourseIds);
        abort_unless(count($selectedCourseIds) === $scopedCourses->count(), 403);

        $classDepartmentId = (int) $classroom->program?->department_id;
        $hasCrossDepartmentCourse = $scopedCourses->contains(fn (Course $course) => (int) $course->department_id !== $classDepartmentId);
        abort_unless(! $hasCrossDepartmentCourse, 403);

        DB::transaction(function () use ($classroom, $selectedCourseIds): void {
            DB::table('class_course')->where('class_id', $classroom->id)->delete();

            foreach ($selectedCourseIds as $courseId) {
                DB::table('class_course')->insert([
                    'class_id' => $classroom->id,
                    'course_id' => $courseId,
                    'assigned_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return redirect()->route('coordinator.courses.assign.edit', ['class_id' => $classroom->id])->with('status', 'Class course assignments updated.');
    }

    private function scopedClasses()
    {
        return Classroom::query()
            ->whereHas('program', fn ($query) => $query->whereIn('department_id', $this->departmentIds()))
            ->with(['program.department', 'level'])
            ->orderBy('name')
            ->get();
    }

    private function scopedCourses()
    {
        return Course::query()
            ->whereIn('department_id', $this->departmentIds())
            ->orderBy('title')
            ->get();
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
