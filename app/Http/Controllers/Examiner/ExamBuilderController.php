<?php

namespace App\Http\Controllers\Examiner;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\ExaminerCourseAssignment;
use App\Models\ExamSection;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Term;
use App\Services\ExamAiPromptBuilder;
use App\Services\ExamAiQuestionGenerator;
use App\Services\ExamLifecycleService;
use App\Services\ExamQuestionImportValidator;
use App\Services\ExamRedisService;
use App\Services\SystemSettingsService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamBuilderController extends Controller
{
    private function bumpExamConfigCache(Quiz $exam): void
    {
        app(ExamRedisService::class)->forgetExamConfig((int) $exam->id);
    }

    private function assertExamDraftForContentMutations(Quiz $exam): void
    {
        abort_if($exam->status === 'archived', 403, 'Archived exams are read-only. Clone this exam to create a new draft.');
        abort_if($exam->status === 'published', 403, 'Published exams are locked. Unpublish or clone to edit content.');
    }

    private function assertExamDraftForSchedule(Quiz $exam): void
    {
        abort_unless($exam->status === 'draft', 403, 'Only draft exams can change the start/end window.');
    }

    private function assertExamNotArchived(Quiz $exam): void
    {
        abort_if($exam->status === 'archived', 403, 'Archived exams are read-only.');
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Quiz::class);

        $user = $request->user();
        $yearFilter = (int) $request->integer('academic_year_id');
        if ($yearFilter <= 0) {
            $yearFilter = (int) (AcademicYear::activeForUniversity((int) $user->university_id)?->id ?? 0);
        }

        $exams = Quiz::query()
            ->whereIn('course_id', $this->manageableCourseIds($request))
            ->with(['course', 'academicYear'])
            ->when($yearFilter > 0, function ($q) use ($yearFilter) {
                $q->where(function ($q2) use ($yearFilter) {
                    $q2->whereNull('academic_year_id')
                        ->orWhere('academic_year_id', $yearFilter);
                });
            })
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('examiner.exams.index', [
            'exams' => $exams,
            'academicYears' => AcademicYear::query()
                ->where('university_id', $user->university_id)
                ->orderByDesc('start_date')
                ->get(['id', 'name', 'is_active']),
            'selectedAcademicYearId' => $yearFilter > 0 ? $yearFilter : null,
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

        $activeYear = AcademicYear::activeForUniversity((int) $user->university_id);
        $activeTerm = $activeYear !== null ? Term::activeForAcademicYear($activeYear->id) : null;

        $quiz = Quiz::create([
            'university_id' => $user->university_id,
            'academic_year_id' => $activeYear?->id,
            'term_id' => $activeTerm?->id,
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

    public function builder(Request $request, Quiz $exam, SystemSettingsService $systemSettings): View
    {
        $this->authorize('view', $exam);

        $exam->load([
            'sections' => fn ($q) => $q->orderBy('section_order'),
            'sections.questions' => fn ($q) => $q->orderBy('question_order'),
        ]);

        $importPreview = session('exam_question_import_'.$exam->id);

        $poolQuestionTotal = Question::query()->where('quiz_id', $exam->id)->count();
        $poolApprovedCount = Question::query()->where('quiz_id', $exam->id)->where('pool_status', 'approved')->count();

        return view('examiner.exams.builder', [
            'exam' => $exam,
            'questionTypes' => ['mcq', 'true_false', 'fill_blank', 'essay'],
            'aiEnabled' => $systemSettings->getBool('enable_ai', true),
            'importPreview' => is_array($importPreview) ? $importPreview : null,
            'canEditContent' => $exam->status === 'draft',
            'canEditSchedule' => $exam->status === 'draft',
            'canEditDelivery' => $exam->status === 'draft',
            'canEditPool' => $exam->status !== 'archived',
            'poolQuestionTotal' => $poolQuestionTotal,
            'poolApprovedCount' => $poolApprovedCount,
        ]);
    }

    public function updateDeliverySettings(Request $request, Quiz $exam): RedirectResponse
    {
        $this->authorize('update', $exam);
        abort_unless($exam->status === 'draft', 403, 'Only draft exams can change delivery settings.');

        $validated = $request->validate([
            'questions_per_student' => ['required', 'integer', 'min:1', 'max:500'],
            'randomize_questions' => ['sometimes', 'boolean'],
            'randomize_options' => ['sometimes', 'boolean'],
        ]);

        $approved = Question::query()
            ->where('quiz_id', $exam->id)
            ->where('pool_status', 'approved')
            ->count();

        if ((int) $validated['questions_per_student'] > $approved) {
            throw ValidationException::withMessages([
                'questions_per_student' => ['Cannot exceed approved question count ('.$approved.').'],
            ]);
        }

        $exam->update([
            'questions_per_student' => (int) $validated['questions_per_student'],
            'randomize_questions' => $request->boolean('randomize_questions'),
            'randomize_options' => $request->boolean('randomize_options'),
        ]);

        $this->bumpExamConfigCache($exam->fresh());

        return back()->with('status', 'Delivery settings updated.');
    }

    public function updateQuestionPoolStatus(Request $request, Quiz $exam, Question $question): RedirectResponse
    {
        $this->authorize('update', $exam);
        $this->assertExamNotArchived($exam);
        abort_unless((int) $question->quiz_id === (int) $exam->id, 404);

        $validated = $request->validate([
            'pool_status' => ['required', 'string', 'in:draft,approved,archived'],
        ]);

        $question->update(['pool_status' => $validated['pool_status']]);

        $this->bumpExamConfigCache($exam->fresh());

        return back()->with('status', 'Question pool status updated.');
    }

    public function publish(Request $request, Quiz $exam, ExamLifecycleService $lifecycle): RedirectResponse
    {
        $this->authorize('update', $exam);

        $lifecycle->publish($exam->fresh());

        return back()->with('status', 'Exam published.');
    }

    public function unpublish(Request $request, Quiz $exam, ExamLifecycleService $lifecycle): RedirectResponse
    {
        $this->authorize('update', $exam);

        $lifecycle->unpublish($exam->fresh());

        return back()->with('status', 'Exam moved back to draft.');
    }

    public function archive(Request $request, Quiz $exam, ExamLifecycleService $lifecycle): RedirectResponse
    {
        $this->authorize('update', $exam);

        $lifecycle->archive($exam->fresh());

        return back()->with('status', 'Exam archived. It is now read-only.');
    }

    public function cloneExam(Request $request, Quiz $exam, ExamLifecycleService $lifecycle): RedirectResponse
    {
        $this->authorize('update', $exam);

        $user = $request->user();
        $copy = $lifecycle->cloneToDraft($exam->fresh(), (int) $user->id, (int) $user->university_id);

        return redirect()
            ->route('examiner.exams.builder', $copy)
            ->with('status', 'Exam duplicated as a new draft.');
    }

    public function updateSchedule(Request $request, Quiz $exam): RedirectResponse
    {
        $this->authorize('update', $exam);
        $this->assertExamDraftForSchedule($exam);

        $validated = $request->validate([
            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date'],
        ]);

        $start = isset($validated['start_time']) ? Carbon::parse($validated['start_time']) : null;
        $end = isset($validated['end_time']) ? Carbon::parse($validated['end_time']) : null;

        if ($start !== null && $end !== null && $end->lt($start)) {
            return back()->withErrors(['end_time' => 'End time must be on or after start time.'])->withInput();
        }

        $exam->update([
            'start_time' => $start,
            'end_time' => $end,
        ]);

        $this->bumpExamConfigCache($exam->fresh());

        return back()->with('status', 'Exam window updated.');
    }

    public function storeSection(Request $request, Quiz $exam): RedirectResponse
    {
        $this->authorize('update', $exam);
        $this->assertExamDraftForContentMutations($exam);

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
        $this->assertExamDraftForContentMutations($exam);
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
                'pool_status' => 'draft',
            ]);

            $total = (float) Question::query()->where('quiz_id', $exam->id)->sum('marks');
            $exam->update(['total_marks' => $total]);
        });

        $this->bumpExamConfigCache($exam->fresh());

        return back()->with('status', 'Question saved.');
    }

    public function previewQuestionImport(Request $request, Quiz $exam, ExamQuestionImportValidator $validator): RedirectResponse
    {
        $this->authorize('update', $exam);
        $this->assertExamDraftForContentMutations($exam);

        $validated = $request->validate([
            'import_json' => ['required', 'string', 'max:500000'],
        ]);

        $result = $validator->validateJsonString($validated['import_json']);
        if (! $result['ok']) {
            return back()
                ->withErrors(['import_json' => implode("\n", $result['errors'])])
                ->withInput();
        }

        session()->put('exam_question_import_'.$exam->id, [
            'sections' => $result['sections'],
            'source' => 'paste',
        ]);

        return back()->with('status', 'Import preview ready — review below and save.');
    }

    public function cancelQuestionImport(Request $request, Quiz $exam): RedirectResponse
    {
        $this->authorize('update', $exam);
        $this->assertExamDraftForContentMutations($exam);
        session()->forget('exam_question_import_'.$exam->id);

        return back()->with('status', 'Import preview cleared.');
    }

    public function commitQuestionImport(Request $request, Quiz $exam): RedirectResponse
    {
        $this->authorize('update', $exam);
        $this->assertExamDraftForContentMutations($exam);

        $bundle = session('exam_question_import_'.$exam->id);
        if (! is_array($bundle) || ! isset($bundle['sections']) || ! is_array($bundle['sections'])) {
            return back()->withErrors(['import_json' => 'No import preview found. Paste JSON and preview again.']);
        }

        $this->persistImportedSections($exam, $bundle['sections']);
        session()->forget('exam_question_import_'.$exam->id);

        $this->bumpExamConfigCache($exam->fresh());

        return back()->with('status', 'Imported questions saved.');
    }

    public function buildAiPrompt(Request $request, Quiz $exam, ExamAiPromptBuilder $promptBuilder): RedirectResponse
    {
        $this->authorize('update', $exam);
        $this->assertExamDraftForContentMutations($exam);

        $validated = $request->validate([
            'ai_topic' => ['required', 'string', 'max:2000'],
            'ai_count' => ['required', 'integer', 'min:1', 'max:50'],
            'ai_question_types' => ['nullable', 'array'],
            'ai_question_types.*' => ['string', 'in:mcq,true_false,fill_blank,essay'],
            'ai_difficulty' => ['nullable', 'string', 'max:120'],
            'ai_marks' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);

        $prompt = $promptBuilder->build([
            'topic' => $validated['ai_topic'],
            'count' => (int) $validated['ai_count'],
            'types' => $validated['ai_question_types'] ?? ['mcq'],
            'difficulty' => $validated['ai_difficulty'] ?? 'mixed',
            'marks_per_question' => (float) ($validated['ai_marks'] ?? 1),
        ]);

        return back()->with('generated_ai_prompt', $prompt)->withInput();
    }

    public function generateWithAi(Request $request, Quiz $exam, ExamAiQuestionGenerator $generator, SystemSettingsService $systemSettings): RedirectResponse
    {
        $this->authorize('update', $exam);
        $this->assertExamDraftForContentMutations($exam);

        if (! $systemSettings->getBool('enable_ai', true)) {
            return back()->withErrors(['ai' => 'AI generation is turned off in system settings.']);
        }

        $request->merge([
            'ai_custom_prompt' => trim((string) $request->input('ai_custom_prompt', '')),
        ]);

        $validated = $request->validate([
            'ai_custom_prompt' => ['nullable', 'string', 'max:16000'],
            'ai_topic' => ['required_without:ai_custom_prompt', 'nullable', 'string', 'max:2000'],
            'ai_count' => ['required_without:ai_custom_prompt', 'nullable', 'integer', 'min:1', 'max:50'],
            'ai_question_types' => ['nullable', 'array'],
            'ai_question_types.*' => ['string', 'in:mcq,true_false,fill_blank,essay'],
            'ai_difficulty' => ['nullable', 'string', 'max:120'],
            'ai_marks' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);

        $custom = trim((string) ($validated['ai_custom_prompt'] ?? ''));
        if ($custom !== '') {
            $prompt = $custom;
        } else {
            $prompt = app(ExamAiPromptBuilder::class)->build([
                'topic' => (string) ($validated['ai_topic'] ?? ''),
                'count' => (int) ($validated['ai_count'] ?? 5),
                'types' => $validated['ai_question_types'] ?? ['mcq'],
                'difficulty' => $validated['ai_difficulty'] ?? 'mixed',
                'marks_per_question' => (float) ($validated['ai_marks'] ?? 1),
            ]);
        }

        $result = $generator->generateFromPrompt($prompt);
        if (! $result['ok']) {
            return back()->withErrors(['ai' => implode("\n", $result['errors'])])->withInput();
        }

        session()->put('exam_question_import_'.$exam->id, [
            'sections' => $result['sections'],
            'source' => 'ai',
        ]);

        return back()->with('status', 'AI draft validated — review preview below before saving.');
    }

    /**
     * @param  list<array{title: string, questions: list<array<string, mixed>>}>  $sections
     */
    private function persistImportedSections(Quiz $exam, array $sections): void
    {
        DB::transaction(function () use ($exam, $sections): void {
            $baseOrder = (int) ExamSection::query()->where('exam_id', $exam->id)->max('section_order');

            foreach ($sections as $sec) {
                $baseOrder++;
                $section = ExamSection::query()->create([
                    'exam_id' => $exam->id,
                    'title' => $sec['title'],
                    'section_order' => $baseOrder,
                ]);

                $qOrder = 0;
                foreach ($sec['questions'] as $q) {
                    $qOrder++;
                    Question::query()->create([
                        'quiz_id' => $exam->id,
                        'section_id' => $section->id,
                        'question_text' => $q['question_text'],
                        'type' => $q['type'],
                        'options' => $q['options'],
                        'correct_answer' => $q['correct_answer'],
                        'answer_schema' => $q['answer_schema'],
                        'marks' => $q['marks'],
                        'question_order' => $qOrder,
                        'pool_status' => 'draft',
                    ]);
                }
            }

            $total = (float) Question::query()->where('quiz_id', $exam->id)->sum('marks');
            $exam->update(['total_marks' => $total]);
        });
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
