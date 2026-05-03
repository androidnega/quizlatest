<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CourseMaterial;
use App\Models\PracticeAttempt;
use App\Models\PracticeQuiz;
use App\Services\PracticeAttemptGradingService;
use App\Services\PracticeModuleSettings;
use App\Services\PracticeQuizGenerationService;
use App\Services\StudentCourseAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StudentPracticeQuizController extends Controller
{
    public function index(PracticeModuleSettings $practice): View|RedirectResponse
    {
        $practice->assertStudentPracticeOrAbort();

        $user = auth()->user();

        $quizzes = PracticeQuiz::query()
            ->where('student_id', $user->id)
            ->with(['course:id,code,title'])
            ->withCount('questions')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('student.practice.quizzes.index', [
            'quizzes' => $quizzes,
        ]);
    }

    public function create(PracticeModuleSettings $practice): View|RedirectResponse
    {
        $practice->assertStudentPracticeOrAbort();

        $user = auth()->user();

        $materialRows = CourseMaterial::query()
            ->visibleToStudent($user)
            ->with(['course:id,code,title'])
            ->orderBy('title')
            ->get();

        return view('student.practice.quizzes.create', [
            'materialRows' => $materialRows,
        ]);
    }

    public function store(
        Request $request,
        PracticeModuleSettings $practice,
        PracticeQuizGenerationService $generator,
        StudentCourseAccessService $courseAccess,
    ): RedirectResponse {
        $practice->assertStudentPracticeOrAbort();
        $practice->assertAiPracticeQuizOrAbort();

        $user = $request->user();

        $validated = $request->validate([
            'course_material_id' => ['required', 'integer'],
            'quiz_type' => ['required', 'string', 'in:mixed,mcq,true_false,fill_blank,essay'],
            'difficulty' => ['required', 'string', 'in:easy,medium,hard'],
            'question_count' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        $material = CourseMaterial::query()
            ->visibleToStudent($user)
            ->whereKey((int) $validated['course_material_id'])
            ->firstOrFail();

        $courseId = (int) $material->course_id;
        abort_unless($courseAccess->canAccessCourse($user, $courseId), 403);

        try {
            $quiz = $generator->generate(
                $user,
                $courseId,
                $user->class_id !== null ? (int) $user->class_id : null,
                (int) $material->id,
                $validated['quiz_type'],
                $validated['difficulty'],
                (int) $validated['question_count'],
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return redirect()
                ->route('student.practice.quizzes.create')
                ->withErrors(['generate' => $e->getMessage()]);
        }

        return redirect()
            ->route('student.practice.quizzes.show', $quiz)
            ->with('status', __('Practice quiz ready.'));
    }

    public function show(PracticeModuleSettings $practice, PracticeQuiz $practiceQuiz): View|RedirectResponse
    {
        $practice->assertStudentPracticeOrAbort();

        $user = auth()->user();
        abort_unless((int) $practiceQuiz->student_id === (int) $user->id, 403);

        $practiceQuiz->load(['course:id,code,title', 'material', 'questions', 'attempts' => function ($q) {
            $q->where('student_id', auth()->id())->orderByDesc('submitted_at');
        }]);

        return view('student.practice.quizzes.show', [
            'quiz' => $practiceQuiz,
        ]);
    }

    public function take(PracticeModuleSettings $practice, PracticeQuiz $practiceQuiz): View|RedirectResponse
    {
        $practice->assertStudentPracticeOrAbort();

        $user = auth()->user();
        abort_unless((int) $practiceQuiz->student_id === (int) $user->id, 403);
        abort_unless($practiceQuiz->status === PracticeQuiz::STATUS_READY, 422);

        $practiceQuiz->load('questions');

        return view('student.practice.quizzes.take', [
            'quiz' => $practiceQuiz,
        ]);
    }

    public function submit(
        Request $request,
        PracticeModuleSettings $practice,
        PracticeQuiz $practiceQuiz,
        PracticeAttemptGradingService $grading,
    ): RedirectResponse {
        $practice->assertStudentPracticeOrAbort();

        $user = $request->user();
        abort_unless((int) $practiceQuiz->student_id === (int) $user->id, 403);
        abort_unless($practiceQuiz->status === PracticeQuiz::STATUS_READY, 422);

        $practiceQuiz->load('questions');
        $rules = [];
        foreach ($practiceQuiz->questions as $q) {
            $rules['answers.'.$q->id] = ['nullable'];
        }
        $request->validate($rules);

        /** @var array<int, mixed> $answers */
        $answers = $request->input('answers', []);

        $attempt = $grading->gradeAndStore($practiceQuiz, $user, $answers);

        return redirect()
            ->route('student.practice.quizzes.result', [$practiceQuiz, $attempt])
            ->with('status', __('Attempt recorded.'));
    }

    public function result(
        PracticeModuleSettings $practice,
        PracticeQuiz $practiceQuiz,
        PracticeAttempt $attempt,
    ): View|RedirectResponse {
        $practice->assertStudentPracticeOrAbort();

        $user = auth()->user();
        abort_unless((int) $practiceQuiz->student_id === (int) $user->id, 403);
        abort_unless((int) $attempt->student_id === (int) $user->id, 403);
        abort_unless((int) $attempt->practice_quiz_id === (int) $practiceQuiz->id, 404);

        $attempt->load(['answers.question']);

        return view('student.practice.quizzes.result', [
            'quiz' => $practiceQuiz,
            'attempt' => $attempt,
        ]);
    }

    public function destroy(PracticeModuleSettings $practice, PracticeQuiz $practiceQuiz): RedirectResponse
    {
        $practice->assertStudentPracticeOrAbort();

        $user = auth()->user();
        abort_unless((int) $practiceQuiz->student_id === (int) $user->id, 403);

        $practiceQuiz->delete();

        return redirect()
            ->route('student.practice.quizzes.index')
            ->with('status', __('Practice quiz removed.'));
    }
}
