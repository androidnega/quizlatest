<?php

namespace App\Http\Controllers\Examiner;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\ExaminerCourseAssignment;
use App\Models\ExamSection;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamBuilderController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Quiz::class);

        $exams = Quiz::query()
            ->whereIn('course_id', $this->manageableCourseIds($request))
            ->with('course')
            ->orderByDesc('updated_at')
            ->paginate(15);

        return view('examiner.exams.index', [
            'exams' => $exams,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Quiz::class);

        $courses = Course::query()
            ->whereIn('id', $this->manageableCourseIds($request))
            ->orderBy('title')
            ->get(['id', 'title', 'code']);

        abort_if($courses->isEmpty(), 403, 'No courses available for exam creation in your scope.');

        return view('examiner.exams.create', [
            'courses' => $courses,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Quiz::class);

        $validated = $request->validate([
            'course_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'assessment_type' => ['nullable', 'string', 'in:quiz,mid,exam,assignment'],
        ]);

        $course = Course::query()->find((int) $validated['course_id']);
        abort_if($course === null, 404);
        $this->authorize('update', $course);

        $user = $request->user();

        $quiz = Quiz::create([
            'university_id' => $user->university_id,
            'course_id' => (int) $validated['course_id'],
            'created_by' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'assessment_type' => $validated['assessment_type'] ?? 'exam',
            'status' => 'draft',
            'duration_minutes' => (int) $validated['duration_minutes'],
            'total_marks' => 0,
        ]);

        return redirect()
            ->route('examiner.exams.builder', $quiz)
            ->with('status', 'Exam created. Add sections and questions below.');
    }

    public function builder(Request $request, Quiz $exam): View
    {
        $this->authorize('view', $exam);

        $exam->load([
            'sections' => fn ($q) => $q->orderBy('section_order'),
            'sections.questions' => fn ($q) => $q->orderBy('question_order'),
        ]);

        return view('examiner.exams.builder', [
            'exam' => $exam,
            'questionTypes' => ['mcq', 'true_false', 'fill_blank', 'essay'],
        ]);
    }

    public function storeSection(Request $request, Quiz $exam): RedirectResponse
    {
        $this->authorize('update', $exam);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $nextOrder = (int) ExamSection::query()->where('exam_id', $exam->id)->max('section_order') + 1;

        ExamSection::create([
            'exam_id' => $exam->id,
            'title' => $validated['title'],
            'section_order' => $nextOrder,
        ]);

        return back()->with('status', 'Section added.');
    }

    public function storeQuestion(Request $request, Quiz $exam, ExamSection $section): RedirectResponse
    {
        $this->authorize('update', $exam);
        abort_unless((int) $section->exam_id === (int) $exam->id, 404);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:mcq,true_false,fill_blank,essay'],
            'question_text' => ['required', 'string'],
            'marks' => ['required', 'numeric', 'min:0'],
            'options' => ['nullable', 'array'],
            'options.*' => ['nullable', 'string', 'max:2000'],
            'correct_mcq' => ['nullable'],
            'correct_true_false' => ['nullable', 'in:0,1'],
            'correct_blanks' => ['nullable', 'string'],
        ]);

        $type = $validated['type'];
        $options = null;
        $correct = null;

        if ($type === 'mcq') {
            $options = array_values(array_filter($validated['options'] ?? [], fn ($o) => $o !== null && trim((string) $o) !== ''));
            abort_unless(count($options) >= 2, 422, 'MCQ requires at least two options.');
            $selected = $request->input('correct_mcq', []);
            $selected = is_array($selected) ? $selected : [];
            $indices = [];
            foreach ($selected as $idx) {
                if (is_numeric($idx)) {
                    $indices[] = (int) $idx;
                }
            }
            $indices = array_values(array_unique(array_filter($indices, fn ($i) => $i >= 0 && $i < count($options))));
            abort_unless($indices !== [], 422, 'Select at least one correct option for MCQ.');
            $correct = $indices;
        } elseif ($type === 'true_false') {
            $correct = $validated['correct_true_false'] === '1';
        } elseif ($type === 'fill_blank') {
            $lines = preg_split('/\r\n|\r|\n/', (string) ($validated['correct_blanks'] ?? ''));
            $correct = array_values(array_filter(array_map('trim', $lines ?: []), fn ($s) => $s !== ''));
            abort_unless(count($correct) >= 1, 422, 'Provide at least one acceptable answer (one per line).');
        } else {
            $correct = null;
        }

        $nextQ = (int) Question::query()->where('section_id', $section->id)->max('question_order') + 1;

        DB::transaction(function () use ($exam, $section, $validated, $type, $options, $correct, $nextQ): void {
            Question::create([
                'quiz_id' => $exam->id,
                'section_id' => $section->id,
                'question_text' => $validated['question_text'],
                'type' => $type,
                'options' => $options,
                'correct_answer' => $correct,
                'answer_schema' => null,
                'marks' => $validated['marks'],
                'question_order' => $nextQ,
            ]);

            $total = (float) Question::query()->where('quiz_id', $exam->id)->sum('marks');
            $exam->update(['total_marks' => $total]);
        });

        return back()->with('status', 'Question saved.');
    }

    /**
     * @return array<int, int>
     */
    private function coordinatorDepartmentIds(Request $request): array
    {
        return $request->user()
            ->coordinatorAssignments()
            ->where('is_active', true)
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Examiner-assigned courses plus any course in the coordinator's departments.
     *
     * @return array<int, int>
     */
    private function manageableCourseIds(Request $request): array
    {
        $fromAssignments = ExaminerCourseAssignment::query()
            ->where('examiner_user_id', $request->user()->id)
            ->where('is_active', true)
            ->pluck('course_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $fromDepartments = Course::query()
            ->whereIn('department_id', $this->coordinatorDepartmentIds($request))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge($fromAssignments, $fromDepartments)));
    }
}
