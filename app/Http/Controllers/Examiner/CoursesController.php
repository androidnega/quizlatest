<?php

namespace App\Http\Controllers\Examiner;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\ExaminerCourseAssignment;
use App\Models\Quiz;
use App\Services\PracticeModuleSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CoursesController extends Controller
{
    public function index(Request $request, PracticeModuleSettings $practice): View
    {
        $this->authorize('viewAny', Quiz::class);

        $user = $request->user();

        $assignedCourses = Course::query()
            ->whereIn('id', ExaminerCourseAssignment::query()
                ->where('examiner_user_id', $user->id)
                ->where('is_active', true)
                ->pluck('course_id'))
            ->with([
                'classrooms' => fn ($q) => $q->with('level:id,name,code')->orderBy('name'),
            ])
            ->withCount([
                'courseMaterials as outlines_ready_count' => fn ($q) => $q
                    ->where('material_kind', CourseMaterial::KIND_COURSE_OUTLINE)
                    ->where('status', CourseMaterial::STATUS_READY),
                'courseMaterials as materials_ready_count' => fn ($q) => $q->where('status', CourseMaterial::STATUS_READY),
            ])
            ->orderBy('code')
            ->get(['id', 'title', 'code']);

        return view('examiner.courses.index', [
            'assignedCourses' => $assignedCourses,
            'materialUploadsEnabled' => $practice->courseMaterialUploadsEnabled(),
        ]);
    }
}
