<?php

namespace App\Http\Controllers\Examiner;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\ExaminerCourseAssignment;
use App\Models\ExamSection;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Term;
use App\Models\User;
use App\Services\ExamAiPromptBuilder;
use App\Services\ExamAiQuestionGenerator;
use App\Services\ExamAssessmentDocumentTextExtractor;
use App\Services\ExamLifecycleService;
use App\Services\ExamQuestionImportValidator;
use App\Services\ExamRedisService;
use App\Services\ProctoringOrchestratorService;
use App\Services\SystemExamPolicyService;
use App\Services\SystemSettingsService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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

    private function assertQuestionGenerationUnlocked(Quiz $exam, string $errorKey = 'import_json'): void
    {
        $hasSavedQuestions = Question::query()->where('quiz_id', $exam->id)->exists();
        if ($hasSavedQuestions) {
            throw ValidationException::withMessages([
                $errorKey => ['Question generation is locked for this assessment. Create a new quiz to generate again.'],
            ]);
        }
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Quiz::class);

        $user = $request->user();
        $yearFilter = (int) $request->integer('academic_year_id');
        if ($yearFilter <= 0) {
            $yearFilter = (int) (AcademicYear::activeForUniversity((int) $user->university_id)?->id ?? 0);
        }

        $tab = $request->query('tab', 'active');
        $tab = $tab === 'ended' ? 'ended' : 'active';

        $manageableCourseIds = $this->manageableCourseIds($request);
        $courseIdFilter = (int) $request->integer('course_id');
        $filterCourse = null;
        if ($courseIdFilter > 0 && in_array($courseIdFilter, $manageableCourseIds, true)) {
            $filterCourse = Course::query()
                ->whereKey($courseIdFilter)
                ->where('university_id', $user->university_id)
                ->first(['id', 'code', 'title']);
            if ($filterCourse === null) {
                $courseIdFilter = 0;
            }
        } else {
            $courseIdFilter = 0;
        }

        $examQuery = Quiz::query()
            ->when($user->role === 'examiner', fn ($q) => $q->where('created_by', $user->id))
            ->when($user->role !== 'examiner', fn ($q) => $q->whereIn('course_id', $manageableCourseIds))
            ->when($courseIdFilter > 0, fn ($q) => $q->where('course_id', $courseIdFilter))
            ->with(['course.classrooms' => fn ($q) => $q->with('level:id,name,code')->orderBy('name'), 'academicYear'])
            ->withCount('questions')
            ->when($yearFilter > 0, function ($q) use ($yearFilter) {
                $q->where(function ($q2) use ($yearFilter) {
                    $q2->whereNull('academic_year_id')
                        ->orWhere('academic_year_id', $yearFilter);
                });
            })
            ->when($tab === 'active', fn ($q) => $q->whereIn('status', ['draft', 'published']))
            ->when($tab === 'ended', fn ($q) => $q->where('status', 'archived'))
            ->orderByDesc('updated_at');

        $exams = $examQuery->paginate(15)->withQueryString();

        return view('examiner.exams.index', [
            'exams' => $exams,
            'examsTab' => $tab,
            'filterCourse' => $filterCourse,
            'filterCourseId' => $courseIdFilter > 0 ? $courseIdFilter : null,
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

        $manageableCourseIds = $this->manageableCourseIds($request);
        $classroomOptions = $this->classroomOptionsForCourses($request->user(), $manageableCourseIds);

        $systemSettings = app(SystemSettingsService::class);
        $aiEnabled = $systemSettings->getBool('enable_ai', true);

        $questionSourceOld = (string) $request->old('question_source', 'later');
        if (! $aiEnabled && $questionSourceOld === 'ai_generate') {
            $questionSourceOld = 'later';
        }

        $examCreateAlpine = [
            'rows' => $classroomOptions,
            'courseId' => (int) ($request->old('course_id', $request->query('course_id')) ?: ($courses->first()->id ?? 0)),
            'selectedClassIds' => array_values(array_map('intval', $request->old('classroom_ids', []) ?: [])),
            'source' => $questionSourceOld,
            'pastePromptTopics' => (string) $request->old('paste_prompt_topics', ''),
            'pastePromptCount' => (int) $request->old('paste_prompt_count', 10),
            'importJsonDraft' => (string) $request->old('import_json', ''),
            'validateImportUrl' => route('examiner.exams.create.validate-import-json'),
            'csrfToken' => csrf_token(),
        ];

        return view('examiner.exams.create', [
            'courses' => $courses,
            'classroomOptions' => $classroomOptions,
            'aiEnabled' => $aiEnabled,
            'examCreateAlpine' => $examCreateAlpine,
        ]);
    }

    public function validateCreateImportJson(Request $request, ExamQuestionImportValidator $importValidator): JsonResponse
    {
        $this->authorize('create', Quiz::class);

        $validated = $request->validate([
            'import_json' => ['required', 'string', 'max:500000'],
        ]);

        $result = $importValidator->validateJsonString($validated['import_json']);

        if (! $result['ok']) {
            return response()->json([
                'ok' => false,
                'errors' => $result['errors'],
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => __('JSON is valid and will be imported when you create the assessment.'),
        ]);
    }

    public function store(
        Request $request,
        ExamQuestionImportValidator $importValidator,
        ExamAiPromptBuilder $promptBuilder,
        ExamAiQuestionGenerator $aiGenerator,
        ExamLifecycleService $lifecycle,
        SystemSettingsService $systemSettings,
    ): RedirectResponse {
        $this->authorize('create', Quiz::class);

        $validated = $request->validate([
            'course_id' => ['required', 'integer'],
            'classroom_ids' => ['required', 'array', 'min:1'],
            'classroom_ids.*' => ['integer', 'distinct'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'assessment_type' => ['required', 'string', 'in:quiz,mid,exam,assignment'],
            'questions_per_student' => ['nullable', 'integer', 'min:1', 'max:500'],
            'randomize_questions' => ['sometimes', 'boolean'],
            'randomize_options' => ['sometimes', 'boolean'],
            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date', 'after_or_equal:start_time'],
            'question_source' => ['required', 'string', 'in:later,paste_json,ai_generate'],
            'import_json' => ['nullable', 'string', 'max:500000'],
            'paste_prompt_topics' => ['nullable', 'string', 'max:4000'],
            'paste_prompt_count' => ['nullable', 'integer', 'min:1', 'max:250'],
            'ai_topics' => ['nullable', 'string', 'max:4000'],
            'ai_question_count' => ['nullable', 'integer', 'min:1', 'max:250'],
            'ai_question_types' => ['nullable', 'array'],
            'ai_question_types.*' => ['string', 'in:mcq,true_false,fill_blank,essay'],
            'ai_difficulty' => ['nullable', 'string', 'max:120'],
            'ai_marks' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'ai_outline_file' => ['nullable', 'file', 'max:5120', 'mimes:txt,pdf,docx'],
            'activate_now' => ['sometimes', 'boolean'],
            'show_correct_answers_in_results' => ['sometimes', 'boolean'],
        ]);

        $course = Course::query()->find((int) $validated['course_id']);
        abort_if($course === null, 404);
        $this->authorize('update', $course);

        $user = $request->user();
        $manageableCourseIds = $this->manageableCourseIds($request);
        abort_unless(in_array((int) $course->id, $manageableCourseIds, true), 403);

        $classIds = collect($validated['classroom_ids'])->map(fn ($id) => (int) $id)->unique()->values()->all();
        $allowedClassIds = collect($this->classroomOptionsForCourses($user, $manageableCourseIds))->pluck('id')->all();
        foreach ($classIds as $cid) {
            if (! in_array($cid, $allowedClassIds, true)) {
                throw ValidationException::withMessages([
                    'classroom_ids' => [__('One or more class groups are not in your teaching scope.')],
                ]);
            }
        }

        $courseId = (int) $course->id;
        foreach ($classIds as $cid) {
            $linked = DB::table('class_course')
                ->where('class_id', $cid)
                ->where('course_id', $courseId)
                ->exists();
            if (! $linked) {
                throw ValidationException::withMessages([
                    'classroom_ids' => [__('Each selected class group must be enrolled in the chosen course.')],
                ]);
            }
        }

        $source = $validated['question_source'];
        if ($source === 'paste_json') {
            $request->validate([
                'import_json' => ['required', 'string', 'min:3', 'max:500000'],
                'questions_per_student' => ['required', 'integer', 'min:1', 'max:500'],
            ]);
        }

        $combinedAiTopic = null;
        if ($source === 'ai_generate') {
            $request->validate([
                'ai_topics' => ['nullable', 'string', 'max:4000'],
                'ai_question_count' => ['required', 'integer', 'min:1', 'max:250'],
                'questions_per_student' => ['required', 'integer', 'min:1', 'max:500'],
            ]);
            if (! $systemSettings->getBool('enable_ai', true)) {
                throw ValidationException::withMessages([
                    'question_source' => [__('AI generation is turned off for your institution.')],
                ]);
            }

            $topicsPart = trim((string) $request->input('ai_topics', ''));
            $outlineText = '';
            if ($request->hasFile('ai_outline_file')) {
                $outlineText = app(ExamAssessmentDocumentTextExtractor::class)->extractPlainText($request->file('ai_outline_file'));
            }
            if ($topicsPart === '' && $outlineText === '') {
                throw ValidationException::withMessages([
                    'ai_topics' => [__('Add topics or upload an outline (PDF, TXT, or DOCX).')],
                ]);
            }
            if ($topicsPart !== '' && $outlineText !== '') {
                $combinedAiTopic = "Instructor topics:\n".$topicsPart."\n\nCourse outline / uploaded document:\n".$outlineText;
            } elseif ($topicsPart !== '') {
                $combinedAiTopic = $topicsPart;
            } else {
                $combinedAiTopic = "Course outline / uploaded document:\n".$outlineText;
            }
        }

        $year = AcademicYear::activeForUniversity((int) $user->university_id);
        abort_if($year === null, 422, 'No active academic year configured. Ask admin to activate an academic year first.');

        $activeTerm = Term::activeForAcademicYear($year->id);
        $policyEnabled = $systemSettings->getBool('enable_proctoring', true);
        $allowPhone = $policyEnabled && $systemSettings->getBool('phone_detection_enabled', true);
        $allowFullscreen = $policyEnabled && $systemSettings->getBool('fullscreen_required', true);
        $allowAutoSubmit = $policyEnabled && $systemSettings->getBool('auto_submit_enabled', true);

        $initialProctoringSettings = ProctoringOrchestratorService::normalizeProctoringSettings([], null);
        $initialProctoringSettings['phone_detection_enabled'] = $allowPhone ? $request->boolean('enable_phone', true) : false;
        $initialProctoringSettings['fullscreen_enforced'] = $allowFullscreen ? $request->boolean('enable_fullscreen', true) : false;
        $initialProctoringSettings['auto_submit_enabled'] = $allowAutoSubmit ? $request->boolean('enable_auto_submit', true) : false;
        $initialProctoringSettings['show_correct_answers_to_students'] = $request->boolean('show_correct_answers_in_results');

        $start = isset($validated['start_time']) ? Carbon::parse($validated['start_time']) : null;
        $end = isset($validated['end_time']) ? Carbon::parse($validated['end_time']) : null;

        $quiz = Quiz::create([
            'university_id' => $user->university_id,
            'academic_year_id' => $year->id,
            'term_id' => $activeTerm?->id,
            'course_id' => $courseId,
            'created_by' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'assessment_type' => $validated['assessment_type'],
            'status' => 'draft',
            'duration_minutes' => (int) $validated['duration_minutes'],
            'total_marks' => 0,
            'questions_per_student' => isset($validated['questions_per_student']) ? (int) $validated['questions_per_student'] : null,
            'randomize_questions' => $request->boolean('randomize_questions'),
            'randomize_options' => $request->boolean('randomize_options'),
            'proctoring_settings' => $initialProctoringSettings,
            'start_time' => $start,
            'end_time' => $end,
        ]);

        $quiz->targetClassrooms()->sync($classIds);

        $importErrors = null;
        if ($source === 'paste_json') {
            $result = $importValidator->validateJsonString((string) $request->input('import_json', ''));
            if (! $result['ok']) {
                $quiz->delete();

                return back()
                    ->withErrors(['import_json' => implode("\n", $result['errors'])])
                    ->withInput();
            }
            $this->persistImportedSections($quiz, $result['sections']);
            $this->approveAllPoolQuestions($quiz);
            $importErrors = $this->validateQuestionsPerStudentAgainstPool($quiz, (int) $request->input('questions_per_student'));
        } elseif ($source === 'ai_generate') {
            $topic = (string) $combinedAiTopic;
            $prompt = $promptBuilder->build([
                'topic' => $topic,
                'count' => (int) $request->input('ai_question_count'),
                'types' => $request->input('ai_question_types', ['mcq']) ?? ['mcq'],
                'difficulty' => $request->input('ai_difficulty') ?? 'mixed',
                'marks_per_question' => (float) ($request->input('ai_marks') ?? 1),
            ]);
            $gen = $aiGenerator->generateFromPrompt($prompt);
            if (! $gen['ok']) {
                $quiz->delete();

                return back()
                    ->withErrors(['ai_topics' => implode("\n", $gen['errors'])])
                    ->withInput();
            }
            $this->persistImportedSections($quiz, $gen['sections']);
            $this->approveAllPoolQuestions($quiz);
            $importErrors = $this->validateQuestionsPerStudentAgainstPool($quiz, (int) $request->input('questions_per_student'));
        }

        if ($importErrors !== null) {
            $quiz->delete();

            return back()->withErrors(['questions_per_student' => $importErrors])->withInput();
        }

        $quiz->refresh();
        $this->bumpExamConfigCache($quiz);

        $status = __('Assessment saved. Continue in the builder.');
        if ($request->boolean('activate_now')) {
            try {
                $lifecycle->publish($quiz->fresh());
                $status = __('Assessment created and published for the selected class groups.');
            } catch (ValidationException $e) {
                return redirect()
                    ->route('examiner.quizzes.workspace', $quiz)
                    ->withErrors($e->errors())
                    ->with('status', __('Saved as draft — fix the items below, then publish from the builder when ready.'));
            }
        }

        return redirect()
            ->route('examiner.quizzes.workspace', $quiz)
            ->with('status', $status);
    }

    /**
     * @return list<array{id:int, label:string, course_ids:list<int>}>
     */
    private function classroomOptionsForCourses(User $user, array $manageableCourseIds): array
    {
        if ($manageableCourseIds === []) {
            return [];
        }

        $links = DB::table('class_course')
            ->whereIn('course_id', $manageableCourseIds)
            ->select('class_id', 'course_id')
            ->get();

        $byClass = $links->groupBy('class_id');
        $classes = Classroom::query()
            ->whereIn('id', $byClass->keys())
            ->where('university_id', (int) $user->university_id)
            ->orderBy('name')
            ->get(['id', 'name', 'section']);

        $out = [];
        foreach ($classes as $c) {
            $courseIds = $byClass->get($c->id, collect())->pluck('course_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
            $label = trim((string) $c->name).($c->section ? ' · '.trim((string) $c->section) : '');
            $out[] = [
                'id' => (int) $c->id,
                'label' => $label !== '' ? $label : (string) $c->id,
                'course_ids' => $courseIds,
            ];
        }

        return $out;
    }

    private function approveAllPoolQuestions(Quiz $exam): void
    {
        Question::query()->where('quiz_id', $exam->id)->update(['pool_status' => 'approved']);
        $total = (float) Question::query()->where('quiz_id', $exam->id)->sum('marks');
        $exam->update(['total_marks' => $total]);
    }

    /**
     * @return list<string>|null error messages or null if ok
     */
    private function validateQuestionsPerStudentAgainstPool(Quiz $exam, int $perStudent): ?array
    {
        $approved = Question::query()
            ->where('quiz_id', $exam->id)
            ->where('pool_status', 'approved')
            ->count();

        if ($approved < 1) {
            return [__('Add at least one question before setting delivery.')];
        }

        if ($perStudent > $approved) {
            return [__('Questions per student cannot exceed the number of questions in the pool (:count).', ['count' => $approved])];
        }

        return null;
    }

    public function builder(Request $request, Quiz $exam, SystemSettingsService $systemSettings): View
    {
        $this->authorize('view', $exam);

        $exam->load([
            'course:id,code,title',
            'sections' => fn ($q) => $q->orderBy('section_order'),
            'sections.questions' => fn ($q) => $q->orderBy('question_order'),
        ]);

        $importPreview = session('exam_question_import_'.$exam->id);

        $poolQuestionTotal = Question::query()->where('quiz_id', $exam->id)->count();
        $poolApprovedCount = Question::query()->where('quiz_id', $exam->id)->where('pool_status', 'approved')->count();

        $sessionsCount = ExamSession::query()->where('exam_id', $exam->id)->count();

        $shareUrl = route('student.exam.prepare', ['quiz' => $exam->id], absolute: true);
        $tokenSeed = hash('sha256', (string) config('app.key').':'.$exam->id);
        $displayToken = strtoupper(substr($tokenSeed, 0, 8)).'-'.strtoupper(substr($tokenSeed, 8, 8));

        $mobileOnly = (bool) data_get($exam->proctoring_settings, 'mobile_only', false);

        $overviewQuestions = $this->overviewQuestionRows($exam);

        $questionAnalytics = Question::query()
            ->where('quiz_id', $exam->id)
            ->selectRaw('type, COUNT(*) as c')
            ->groupBy('type')
            ->pluck('c', 'type')
            ->all();

        $sessionsWorkspace = null;
        if ($request->user()->can('manageResults', $exam)) {
            $sessionsWorkspace = app(ExamSessionReviewController::class)->buildSessionsWorkspacePayload($request, $exam);
        }

        $examPolicy = app(SystemExamPolicyService::class);
        $examProctoringControls = ProctoringOrchestratorService::normalizeProctoringSettings(
            is_array($exam->proctoring_settings) ? $exam->proctoring_settings : [],
            $exam->id
        );

        $allowedWorkspaceTabs = ['overview', 'sessions', 'scores', 'analytics'];
        $workspaceTab = (string) $request->query('tab', 'overview');
        if (! in_array($workspaceTab, $allowedWorkspaceTabs, true)) {
            $workspaceTab = 'overview';
        }
        if ($workspaceTab === 'sessions' && $sessionsWorkspace === null) {
            $workspaceTab = 'overview';
        }

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
            'generationLocked' => $poolQuestionTotal > 0,
            'sessionsCount' => $sessionsCount,
            'shareUrl' => $shareUrl,
            'displayToken' => $displayToken,
            'mobileOnly' => $mobileOnly,
            'overviewQuestions' => $overviewQuestions,
            'questionAnalytics' => $questionAnalytics,
            'sessionsWorkspace' => $sessionsWorkspace,
            'workspaceTab' => $workspaceTab,
            'proctoringPolicy' => [
                'enabled' => $examPolicy->isProctoringEnabled(),
                'allow_exam_start_snapshot' => $examPolicy->isExamStartSnapshotRequired(),
                'allow_camera_monitoring' => $examPolicy->isCameraMonitoringRequired(),
                'allow_phone' => $systemSettings->getBool('phone_detection_enabled', true),
                'allow_fullscreen' => $systemSettings->getBool('fullscreen_required', true),
                'allow_auto_submit' => $systemSettings->getBool('auto_submit_enabled', true),
            ],
            'examProctoringControls' => $examProctoringControls,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function overviewQuestionRows(Quiz $exam): array
    {
        $rows = [];
        $n = 0;
        foreach ($exam->sections as $section) {
            foreach ($section->questions as $q) {
                $n++;
                $topic = (string) data_get($q->metadata, 'topic', '');
                $aiFlag = (bool) data_get($q->metadata, 'ai_generated')
                    || (bool) data_get($q->metadata, 'ai');

                $rows[] = [
                    'id' => $q->id,
                    'n' => $n,
                    'text' => $q->question_text,
                    'type' => $q->type,
                    'typeLabel' => strtoupper(str_replace('_', ' ', $q->type)),
                    'pool_status' => $q->pool_status,
                    'topic' => $topic,
                    'ai' => $aiFlag,
                    'answer' => $this->questionAnswerPreviewLine($q),
                    'section' => $section->title,
                ];
            }
        }

        return $rows;
    }

    private function questionAnswerPreviewLine(Question $q): string
    {
        if ($q->isMCQ() && is_array($q->options)) {
            $ca = $q->correct_answer;
            $indices = [];
            if (is_array($ca)) {
                foreach ($ca as $v) {
                    if (is_int($v) || (is_string($v) && ctype_digit($v))) {
                        $indices[] = (int) $v;
                    }
                }
            } elseif (is_int($ca) || (is_string($ca) && ctype_digit($ca))) {
                $indices[] = (int) $ca;
            }
            $labels = [];
            foreach ($indices as $idx) {
                $labels[] = ($q->options[$idx] ?? '') !== '' ? (string) $q->options[$idx] : '#'.$idx;
            }

            return $labels !== [] ? implode('; ', $labels) : '—';
        }

        if ($q->isTrueFalse()) {
            if ($q->correct_answer === true) {
                return 'True';
            }
            if ($q->correct_answer === false) {
                return 'False';
            }

            return '—';
        }

        if ($q->isFillBlank() && is_array($q->correct_answer)) {
            return implode(', ', array_map(fn ($v) => (string) $v, $q->correct_answer));
        }

        return '—';
    }

    public function updateDeliverySettings(Request $request, Quiz $exam): RedirectResponse
    {
        $this->authorize('update', $exam);
        abort_unless($exam->status === 'draft', 403, 'Only draft exams can change delivery settings.');

        $approved = Question::query()
            ->where('quiz_id', $exam->id)
            ->where('pool_status', 'approved')
            ->count();

        if ($approved < 1) {
            throw ValidationException::withMessages([
                'questions_per_student' => ['Approve at least one question in the pool before configuring delivery.'],
            ]);
        }

        $validated = $request->validate([
            'questions_per_student' => ['required', 'integer', 'min:1', 'max:500'],
            'randomize_questions' => ['sometimes', 'boolean'],
            'randomize_options' => ['sometimes', 'boolean'],
        ]);

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

    public function updateProctoringExaminerChoices(Request $request, Quiz $exam, SystemSettingsService $systemSettings): RedirectResponse
    {
        $this->authorize('update', $exam);
        abort_unless($exam->status === 'draft', 403, 'Only draft assessments can change proctoring options.');

        $policyEnabled = $systemSettings->getBool('enable_proctoring', true);
        $allowPhone = $policyEnabled && $systemSettings->getBool('phone_detection_enabled', true);
        $allowFullscreen = $policyEnabled && $systemSettings->getBool('fullscreen_required', true);
        $allowAutoSubmit = $policyEnabled && $systemSettings->getBool('auto_submit_enabled', true);

        $request->validate([
            'enable_phone' => ['sometimes', 'boolean'],
            'enable_fullscreen' => ['sometimes', 'boolean'],
            'enable_auto_submit' => ['sometimes', 'boolean'],
        ]);

        $current = is_array($exam->proctoring_settings) ? $exam->proctoring_settings : [];
        $normalized = ProctoringOrchestratorService::normalizeProctoringSettings($current, $exam->id);

        $normalized['phone_detection_enabled'] = $allowPhone ? $request->boolean('enable_phone', true) : false;
        $normalized['fullscreen_enforced'] = $allowFullscreen ? $request->boolean('enable_fullscreen', true) : false;
        $normalized['auto_submit_enabled'] = $allowAutoSubmit ? $request->boolean('enable_auto_submit', true) : false;

        $extras = array_intersect_key($current, array_flip(['show_correct_answers_to_students', 'mobile_only']));
        $merged = array_merge($normalized, $extras);

        $exam->update(['proctoring_settings' => $merged]);

        $this->bumpExamConfigCache($exam->fresh());

        return back()->with('status', __('Proctoring options updated.'));
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

        $total = Question::query()->where('quiz_id', $exam->id)->count();
        $approved = Question::query()->where('quiz_id', $exam->id)->where('pool_status', 'approved')->count();
        $nextStage = $total > 0 && $approved === $total ? 'settings' : 'pool';

        return back()->with('status', 'Question pool status updated.')->with('builder_stage', $nextStage);
    }

    public function bulkUpdateQuestionPoolStatus(Request $request, Quiz $exam): RedirectResponse
    {
        $this->authorize('update', $exam);
        $this->assertExamNotArchived($exam);

        $validated = $request->validate([
            'pool_status' => ['required', 'string', 'in:draft,approved,archived'],
            'mode' => ['required', 'string', 'in:selected,all'],
            'question_ids' => ['nullable', 'array'],
            'question_ids.*' => ['integer'],
        ]);

        $query = Question::query()->where('quiz_id', $exam->id);

        if ($validated['mode'] === 'selected') {
            $ids = collect($validated['question_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values()->all();
            if ($ids === []) {
                return back()->withErrors(['pool_status' => 'Select at least one question for batch update.']);
            }
            $query->whereIn('id', $ids);
        }

        $updated = $query->update(['pool_status' => $validated['pool_status']]);

        $this->bumpExamConfigCache($exam->fresh());

        $total = Question::query()->where('quiz_id', $exam->id)->count();
        $approved = Question::query()->where('quiz_id', $exam->id)->where('pool_status', 'approved')->count();
        $nextStage = $total > 0 && $approved === $total ? 'settings' : 'pool';

        return back()
            ->with('status', $updated > 0 ? "Updated {$updated} question(s)." : 'No questions were updated.')
            ->with('builder_stage', $nextStage);
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
            ->route('examiner.quizzes.workspace', $copy)
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
        $this->assertQuestionGenerationUnlocked($exam, 'import_json');

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
        $this->assertQuestionGenerationUnlocked($exam, 'import_json');
        session()->forget('exam_question_import_'.$exam->id);

        return back()->with('status', 'Import preview cleared.');
    }

    public function commitQuestionImport(Request $request, Quiz $exam): RedirectResponse
    {
        $this->authorize('update', $exam);
        $this->assertExamDraftForContentMutations($exam);
        $this->assertQuestionGenerationUnlocked($exam, 'import_json');

        $bundle = session('exam_question_import_'.$exam->id);
        if (! is_array($bundle) || ! isset($bundle['sections']) || ! is_array($bundle['sections'])) {
            return back()->withErrors(['import_json' => 'No import preview found. Paste JSON and preview again.']);
        }

        $this->persistImportedSections($exam, $bundle['sections']);
        session()->forget('exam_question_import_'.$exam->id);

        $this->bumpExamConfigCache($exam->fresh());

        return back()->with('status', 'Imported questions saved.')->with('builder_stage', 'pool');
    }

    public function buildAiPrompt(Request $request, Quiz $exam, ExamAiPromptBuilder $promptBuilder): RedirectResponse
    {
        $this->authorize('update', $exam);
        $this->assertExamDraftForContentMutations($exam);
        $this->assertQuestionGenerationUnlocked($exam, 'ai');

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
        $this->assertQuestionGenerationUnlocked($exam, 'ai');

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
    private function manageableCourseIds(Request $request): array
    {
        return ExaminerCourseAssignment::query()
            ->where('examiner_user_id', $request->user()->id)
            ->where('is_active', true)
            ->pluck('course_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
