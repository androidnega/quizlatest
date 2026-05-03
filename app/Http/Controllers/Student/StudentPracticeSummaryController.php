<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CourseMaterial;
use App\Models\PracticeSummary;
use App\Services\PracticeModuleSettings;
use App\Services\PracticeSummaryGenerationService;
use App\Services\StudentCourseAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StudentPracticeSummaryController extends Controller
{
    public function index(PracticeModuleSettings $practice): View|RedirectResponse
    {
        $practice->assertStudentPracticeOrAbort();

        $user = auth()->user();

        $summaries = PracticeSummary::query()
            ->where('student_id', $user->id)
            ->with(['course:id,code,title'])
            ->orderByDesc('created_at')
            ->paginate(15);

        $materialRows = CourseMaterial::query()
            ->visibleToStudent($user)
            ->with(['course:id,code,title'])
            ->orderBy('title')
            ->get();

        return view('student.practice.summaries.index', [
            'summaries' => $summaries,
            'materialRows' => $materialRows,
        ]);
    }

    public function store(
        Request $request,
        PracticeModuleSettings $practice,
        PracticeSummaryGenerationService $generator,
        StudentCourseAccessService $courseAccess,
    ): RedirectResponse {
        $practice->assertStudentPracticeOrAbort();
        $practice->assertAiSummaryOrAbort();

        $user = $request->user();

        $validated = $request->validate([
            'course_material_id' => ['required', 'integer'],
        ]);

        $material = CourseMaterial::query()
            ->visibleToStudent($user)
            ->whereKey((int) $validated['course_material_id'])
            ->firstOrFail();

        $courseId = (int) $material->course_id;
        abort_unless($courseAccess->canAccessCourse($user, $courseId), 403);

        try {
            $summary = $generator->generate(
                $user,
                $courseId,
                (int) $material->id,
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return redirect()
                ->route('student.practice.summaries.index')
                ->withErrors(['summary' => $e->getMessage()]);
        }

        return redirect()
            ->route('student.practice.summaries.show', $summary)
            ->with('status', __('Summary generated.'));
    }

    public function show(PracticeModuleSettings $practice, PracticeSummary $practiceSummary): View|RedirectResponse
    {
        $practice->assertStudentPracticeOrAbort();

        abort_unless((int) $practiceSummary->student_id === (int) auth()->id(), 403);

        $practiceSummary->load(['course:id,code,title', 'material']);

        return view('student.practice.summaries.show', [
            'summary' => $practiceSummary,
        ]);
    }
}
