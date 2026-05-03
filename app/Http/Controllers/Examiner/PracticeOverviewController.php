<?php

namespace App\Http\Controllers\Examiner;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\PracticeAttempt;
use App\Models\PracticeQuiz;
use App\Services\ExaminerCourseScopeService;
use App\Services\PracticeModuleSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PracticeOverviewController extends Controller
{
    public function index(
        Request $request,
        PracticeModuleSettings $practice,
        ExaminerCourseScopeService $scope,
    ): View|RedirectResponse {
        $practice->assertExaminerOverviewOrAbort();

        $courseIds = $scope->manageableCourseIds($request->user());
        if ($courseIds === []) {
            return view('examiner.practice-overview.index', [
                'courses' => collect(),
                'selectedCourseId' => null,
                'stats' => [
                    'students' => 0,
                    'quizzes_generated' => 0,
                    'avg_percentage' => null,
                ],
                'topMaterials' => collect(),
            ]);
        }

        $courses = Course::query()
            ->whereIn('id', $courseIds)
            ->orderBy('code')
            ->get(['id', 'code', 'title']);

        $selected = (int) $request->integer('course_id');
        if ($selected > 0 && ! in_array($selected, $courseIds, true)) {
            abort(403);
        }

        $scopeIds = $selected > 0 ? [$selected] : $courseIds;

        $distinctStudents = (int) PracticeQuiz::query()
            ->whereIn('course_id', $scopeIds)
            ->selectRaw('count(distinct student_id) as c')
            ->value('c');

        $quizGenerated = PracticeQuiz::query()
            ->whereIn('course_id', $scopeIds)
            ->where('generated_by_ai', true)
            ->count();

        $avgPct = PracticeAttempt::query()
            ->join('practice_quizzes', 'practice_attempts.practice_quiz_id', '=', 'practice_quizzes.id')
            ->whereIn('practice_quizzes.course_id', $scopeIds)
            ->whereNotNull('practice_attempts.percentage')
            ->avg('practice_attempts.percentage');

        $topMaterials = PracticeQuiz::query()
            ->select('course_material_id', DB::raw('count(*) as c'))
            ->whereIn('course_id', $scopeIds)
            ->whereNotNull('course_material_id')
            ->groupBy('course_material_id')
            ->orderByDesc('c')
            ->limit(5)
            ->get();

        $materialTitles = CourseMaterial::query()
            ->whereIn('id', $topMaterials->pluck('course_material_id')->filter())
            ->pluck('title', 'id');

        $topRows = $topMaterials->map(fn ($row) => [
            'title' => $materialTitles[(int) $row->course_material_id] ?? ('#'.$row->course_material_id),
            'count' => (int) $row->c,
        ]);

        return view('examiner.practice-overview.index', [
            'courses' => $courses,
            'selectedCourseId' => $selected > 0 ? $selected : null,
            'stats' => [
                'students' => $distinctStudents,
                'quizzes_generated' => $quizGenerated,
                'avg_percentage' => $avgPct !== null ? round((float) $avgPct, 2) : null,
            ],
            'topMaterials' => $topRows,
        ]);
    }
}
