<?php

namespace App\Http\Controllers\Examiner;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\ExaminerCourseAssignment;
use App\Models\ExamSection;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Result;
use App\Models\Term;
use App\Models\User;
use App\Services\AssessmentAnalyticsService;
use App\Services\AssignmentEssayAiGradingService;
use App\Services\ExamAiPromptBuilder;
use App\Services\ExamAiQuestionGenerator;
use App\Services\ExamAssessmentDocumentTextExtractor;
use App\Services\ExamLifecycleService;
use App\Services\ExamQuestionImportValidator;
use App\Services\ExamRedisService;
use App\Services\OutlineTopicSuggester;
use App\Services\ProctoringOrchestratorService;
use App\Services\SystemExamPolicyService;
use App\Services\SystemSettingsService;
use App\Support\AssessmentProctoringDefaults;
use App\Support\AssessmentQuestionTypes;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Sleep;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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

        $proctoringFocusRaw = $request->query('proctoring_focus');
        $allowedProctoringFocus = [
            'flagged',
            'auto_submitted',
            'phone_detected',
            'tab_switch_limit',
            'held_results',
            'assignments_grading',
        ];
        $proctoringFocus = is_string($proctoringFocusRaw) && in_array($proctoringFocusRaw, $allowedProctoringFocus, true)
            ? $proctoringFocusRaw
            : null;

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
            // "Active" = drafts + still-deliverable assessments (no end time,
            // or end time / due date still in the future).
            // "Ended" = manually archived OR a published assessment whose
            // end_time / assignment due_at has already passed.
            ->when($tab === 'active', function ($q): void {
                $now = now();
                $q->where(function ($qq) use ($now): void {
                    $qq->where('status', 'draft')
                        ->orWhere(function ($qqq) use ($now): void {
                            $qqq->where('status', 'published')
                                ->where(function ($w) use ($now): void {
                                    $w->where(function ($qw) use ($now): void {
                                        $qw->where('assessment_type', '!=', 'assignment')
                                            ->where(function ($w2) use ($now): void {
                                                $w2->whereNull('end_time')->orWhere('end_time', '>=', $now);
                                            });
                                    })->orWhere(function ($qw) use ($now): void {
                                        $qw->where('assessment_type', 'assignment')
                                            ->where(function ($w2) use ($now): void {
                                                $w2->whereNull('due_at')->orWhere('due_at', '>=', $now);
                                            });
                                    });
                                });
                        });
                });
            })
            ->when($tab === 'ended', function ($q): void {
                $now = now();
                $q->where(function ($qq) use ($now): void {
                    $qq->where('status', 'archived')
                        ->orWhere(function ($qqq) use ($now): void {
                            $qqq->where('status', 'published')
                                ->where(function ($w) use ($now): void {
                                    $w->where(function ($qw) use ($now): void {
                                        $qw->where('assessment_type', '!=', 'assignment')
                                            ->whereNotNull('end_time')
                                            ->where('end_time', '<', $now);
                                    })->orWhere(function ($qw) use ($now): void {
                                        $qw->where('assessment_type', 'assignment')
                                            ->whereNotNull('due_at')
                                            ->where('due_at', '<', $now);
                                    });
                                });
                        });
                });
            })
            ->orderByDesc('updated_at');

        $examQuery
            ->when($proctoringFocus === 'flagged', function ($q): void {
                $q->whereExists(function (Builder $sub): void {
                    $sub->from('exam_sessions')
                        ->whereColumn('exam_sessions.exam_id', 'quizzes.id')
                        ->where(function ($s): void {
                            $s->whereIn('exam_sessions.risk_state', ['suspicious', 'critical', 'locked'])
                                ->orWhereExists(function (Builder $sub2): void {
                                    $sub2->from('results')
                                        ->whereColumn('results.user_id', 'exam_sessions.student_id')
                                        ->whereColumn('results.quiz_id', 'exam_sessions.exam_id')
                                        ->where('results.status', 'held')
                                        ->selectRaw('1');
                                });
                        });
                });
            })
            ->when($proctoringFocus === 'auto_submitted', function ($q): void {
                $q->whereExists(function (Builder $sub): void {
                    $sub->from('exam_sessions')
                        ->whereColumn('exam_sessions.exam_id', 'quizzes.id')
                        ->whereNotNull('exam_sessions.auto_submit_reason_code');
                });
            })
            ->when($proctoringFocus === 'phone_detected', function ($q): void {
                $q->whereExists(function (Builder $sub): void {
                    $sub->from('exam_sessions')
                        ->whereColumn('exam_sessions.exam_id', 'quizzes.id')
                        ->where(function ($s): void {
                            $s->where('exam_sessions.auto_submit_reason_code', 'phone_detected')
                                ->orWhereExists(function (Builder $sub2): void {
                                    $sub2->from('proctoring_events')
                                        ->whereColumn('proctoring_events.user_id', 'exam_sessions.student_id')
                                        ->whereColumn('proctoring_events.quiz_id', 'exam_sessions.exam_id')
                                        ->where('proctoring_events.event_type', 'phone_detected')
                                        ->whereColumn('proctoring_events.metadata->session_id', 'exam_sessions.session_id')
                                        ->selectRaw('1');
                                });
                        });
                });
            })
            ->when($proctoringFocus === 'tab_switch_limit', function ($q): void {
                $q->whereExists(function (Builder $sub): void {
                    $sub->from('exam_sessions')
                        ->whereColumn('exam_sessions.exam_id', 'quizzes.id')
                        ->where('exam_sessions.auto_submit_reason_code', 'tab_switch_limit');
                });
            })
            ->when($proctoringFocus === 'held_results', function ($q): void {
                $q->whereHas('results', fn ($r) => $r->where('status', 'held'));
            })
            ->when($proctoringFocus === 'assignments_grading', function ($q): void {
                $q->where('assessment_type', 'assignment')
                    ->whereHas('results', fn ($r) => $r->whereIn('status', ['held', 'pending_manual']));
            });

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
            'proctoringFocus' => $proctoringFocus,
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
            'assessmentType' => (string) $request->old('assessment_type', 'quiz'),
            'source' => $questionSourceOld,
            'pastePromptTopics' => (string) $request->old('paste_prompt_topics', ''),
            'pastePromptCount' => (int) $request->old('paste_prompt_count', 10),
            'aiQuestionCount' => (int) $request->old('ai_question_count', 10),
            'aiTypeCountsInitial' => is_array($request->old('ai_type_counts')) ? array_map('intval', $request->old('ai_type_counts')) : null,
            'questionsPerStudent' => (int) $request->old('questions_per_student', 10),
            // Reactive seed for the type-in-pool checkbox state. Lets the
            // Alpine `selectedQuestionTypes` array survive a failed submit
            // (old() values) and keeps the per-type counter UI in sync.
            'selectedQuestionTypesInitial' => array_values(array_filter(
                is_array($request->old('selected_question_types', ['mcq', 'true_false', 'fill_blank']))
                    ? $request->old('selected_question_types', ['mcq', 'true_false', 'fill_blank'])
                    : ['mcq', 'true_false', 'fill_blank'],
                static fn ($t) => is_string($t) && $t !== 'essay'
            )),
            'importJsonDraft' => (string) $request->old('import_json', ''),
            'validateImportUrl' => route('examiner.exams.create.validate-import-json'),
            'outlineSuggestTopicsUrl' => route('examiner.exams.create.outline-suggest-topics'),
            'aiGenerateBatchUrl' => route('examiner.exams.create.ai.generate-batch'),
            'aiGenerateBatchSize' => 10,
            'aiTopicsInitial' => (string) $request->old('ai_topics', ''),
            'csrfToken' => csrf_token(),
            'initialWizardStep' => (int) old('wizard_step', 1) === 2 ? 2 : 1,
        ];

        $examPolicy = app(SystemExamPolicyService::class);
        $proctoringPolicy = [
            'enabled' => $examPolicy->isProctoringEnabled(),
            'allow_exam_start_snapshot' => $examPolicy->isExamStartSnapshotRequired(),
            'allow_camera_monitoring' => $examPolicy->isCameraMonitoringRequired(),
            'allow_phone' => $systemSettings->getBool('phone_detection_enabled', true),
            'allow_fullscreen' => $systemSettings->getBool('fullscreen_required', true),
            'allow_auto_submit' => $systemSettings->getBool('auto_submit_enabled', true),
        ];
        $normalizedDefaults = ProctoringOrchestratorService::normalizeProctoringSettings([], null);
        $examProctoringControls = [
            'phone_detection_enabled' => (bool) old('enable_phone', $normalizedDefaults['phone_detection_enabled'] ?? true),
            'fullscreen_enforced' => (bool) old('enable_fullscreen', $normalizedDefaults['fullscreen_enforced'] ?? true),
            'auto_submit_enabled' => (bool) old('enable_auto_submit', $normalizedDefaults['auto_submit_enabled'] ?? true),
        ];

        return view('examiner.exams.create', [
            'courses' => $courses,
            'classroomOptions' => $classroomOptions,
            'aiEnabled' => $aiEnabled,
            'examCreateAlpine' => $examCreateAlpine,
            'proctoringPolicy' => $proctoringPolicy,
            'examProctoringControls' => $examProctoringControls,
        ]);
    }

    public function validateCreateImportJson(Request $request, ExamQuestionImportValidator $importValidator): JsonResponse
    {
        $this->authorize('create', Quiz::class);

        $validated = $request->validate([
            'import_json' => ['required', 'string', 'max:500000'],
            'selected_question_types' => ['sometimes', 'array'],
            'selected_question_types.*' => ['string', 'in:mcq,true_false,fill_blank,essay'],
            // Optional — when sent, the validator also confirms the JSON
            // contains at least this many questions (otherwise students
            // wouldn't be able to draw the required sample).
            'questions_per_student' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:500'],
        ]);

        $allowed = null;
        if (! empty($validated['selected_question_types']) && is_array($validated['selected_question_types'])) {
            $allowed = array_values(array_unique(array_map(
                fn ($t) => is_string($t) ? strtolower(trim($t)) : '',
                $validated['selected_question_types']
            )));
            $allowed = array_values(array_filter($allowed, fn ($t) => $t !== ''));
            if ($allowed === []) {
                $allowed = null;
            }
        }

        $result = $importValidator->validateJsonString($validated['import_json'], $allowed, null);

        if (! $result['ok']) {
            return response()->json([
                'ok' => false,
                'errors' => $result['errors'],
            ], 422);
        }

        // Cross-check question count vs questions_per_student so the import
        // never produces a pool that is too small to draw from. Counted here
        // because the validator returns sections-with-questions, which is
        // the canonical shape we'd persist.
        $sections = is_array($result['sections'] ?? null) ? $result['sections'] : [];
        $totalQuestions = 0;
        $typeBreakdown = ['mcq' => 0, 'true_false' => 0, 'fill_blank' => 0, 'essay' => 0];
        foreach ($sections as $section) {
            $questions = is_array($section['questions'] ?? null) ? $section['questions'] : [];
            $totalQuestions += count($questions);
            foreach ($questions as $question) {
                $type = is_array($question) ? (string) ($question['type'] ?? '') : '';
                if (isset($typeBreakdown[$type])) {
                    $typeBreakdown[$type]++;
                }
            }
        }

        $perStudent = isset($validated['questions_per_student'])
            ? (int) $validated['questions_per_student']
            : 0;
        if ($perStudent > 0 && $totalQuestions < $perStudent) {
            return response()->json([
                'ok' => false,
                'errors' => [
                    __('Pool too small: your JSON contains :pool question(s) but “Questions per student” is set to :n. Add more questions to the JSON or lower the per-student count.', [
                        'pool' => $totalQuestions,
                        'n' => $perStudent,
                    ]),
                ],
            ], 422);
        }

        // Build a friendly summary line ("12 questions ready · 8 MCQ, 4 True/False")
        // so the green success banner actually tells the lecturer what was found.
        $labels = ['mcq' => __('MCQ'), 'true_false' => __('True/False'), 'fill_blank' => __('Fill-in-the-blank'), 'essay' => __('Essay')];
        $breakdownParts = [];
        foreach ($typeBreakdown as $type => $count) {
            if ($count > 0) {
                $breakdownParts[] = trans_choice('{1} :n :label|[2,*] :n :label', $count, ['n' => $count, 'label' => $labels[$type]]);
            }
        }

        $message = trans_choice('{1} :n question ready to import|[2,*] :n questions ready to import', $totalQuestions, ['n' => $totalQuestions]);
        if ($breakdownParts !== []) {
            $message .= ' · '.implode(', ', $breakdownParts);
        }
        if ($perStudent > 0) {
            $message .= ' · '.__('Each student will draw :n at random.', ['n' => $perStudent]);
        }

        return response()->json([
            'ok' => true,
            'message' => $message,
            'pool_count' => $totalQuestions,
            'type_breakdown' => $typeBreakdown,
        ]);
    }

    public function suggestCreateOutlineTopics(
        Request $request,
        ExamAssessmentDocumentTextExtractor $extractor,
        OutlineTopicSuggester $topicSuggester,
    ): JsonResponse {
        $this->authorize('create', Quiz::class);

        $request->validate([
            'ai_outline_file' => ['required', 'file', 'max:5120', 'mimes:txt,pdf,docx,csv'],
        ]);

        try {
            $text = $extractor->extractPlainText($request->file('ai_outline_file'));
        } catch (ValidationException $e) {
            $messages = Arr::flatten($e->errors());

            return response()->json([
                'ok' => false,
                'topics' => [],
                'message' => $messages[0] ?? __('Could not read that file.'),
            ], 422);
        }

        $topics = $topicSuggester->suggestFromPlainText($text, max: 25);

        return response()->json([
            'ok' => true,
            'topics' => $topics,
            // Hand the extracted plain text back so the browser can re-use it
            // for AI batch generation without re-uploading the file each batch.
            'outline_text' => mb_substr($text, 0, 40_000),
        ]);
    }

    /**
     * Generate a single batch of AI questions for the create wizard. Designed
     * to be called repeatedly by the browser so a long total (e.g. 125
     * questions) can be split into small LLM calls with a live progress bar.
     * Never persists anything — sections are returned to the browser and only
     * saved when the wizard form is finally submitted.
     */
    public function aiGenerateBatch(
        Request $request,
        ExamAiPromptBuilder $promptBuilder,
        ExamAiQuestionGenerator $aiGenerator,
        SystemSettingsService $systemSettings,
    ): JsonResponse {
        $this->authorize('create', Quiz::class);

        if (! $systemSettings->getBool('enable_ai', true)) {
            return response()->json([
                'ok' => false,
                'errors' => [__('AI generation is turned off for your institution.')],
            ], 422);
        }

        $validated = $request->validate([
            'ai_topics' => ['nullable', 'string', 'max:4000'],
            'ai_outline_text' => ['nullable', 'string', 'max:60000'],
            'selected_question_types' => ['required', 'array', 'min:1'],
            'selected_question_types.*' => ['string', 'in:mcq,true_false,fill_blank,essay'],
            'ai_question_types' => ['nullable', 'array'],
            'ai_question_types.*' => ['string', 'in:mcq,true_false,fill_blank,essay'],
            // Per-type batch breakdown (e.g. {"mcq": 5, "true_false": 3,
            // "fill_blank": 2}). When sent, it must sum to batch_count and
            // never include essay (essay is manually graded and excluded
            // from AI generation entirely).
            'ai_type_counts' => ['nullable', 'array'],
            'ai_type_counts.mcq' => ['nullable', 'integer', 'min:0', 'max:250'],
            'ai_type_counts.true_false' => ['nullable', 'integer', 'min:0', 'max:250'],
            'ai_type_counts.fill_blank' => ['nullable', 'integer', 'min:0', 'max:250'],
            'ai_difficulty' => ['nullable', 'string', 'max:120'],
            'ai_marks' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'batch_count' => ['required', 'integer', 'min:1', 'max:20'],
            'batch_index' => ['nullable', 'integer', 'min:0', 'max:500'],
            'total_count' => ['nullable', 'integer', 'min:1', 'max:250'],
            'existing_question_texts' => ['nullable', 'array', 'max:300'],
            'existing_question_texts.*' => ['string', 'max:2000'],
        ]);

        $topicsPart = trim((string) ($validated['ai_topics'] ?? ''));
        $outlineText = trim((string) ($validated['ai_outline_text'] ?? ''));
        if ($topicsPart === '' && $outlineText === '') {
            return response()->json([
                'ok' => false,
                'errors' => [__('Add topics or upload an outline before generating questions.')],
            ], 422);
        }

        if ($topicsPart !== '' && $outlineText !== '') {
            $combinedTopic = "Instructor topics:\n".$topicsPart."\n\nCourse outline / uploaded document:\n".$outlineText;
        } elseif ($topicsPart !== '') {
            $combinedTopic = $topicsPart;
        } else {
            $combinedTopic = "Course outline / uploaded document:\n".$outlineText;
        }

        $selectedTypes = AssessmentQuestionTypes::normalizeFromRequest($validated['selected_question_types']);

        try {
            $aiTypes = AssessmentQuestionTypes::intersectAiTypesWithAllowed(
                $validated['ai_question_types'] ?? null,
                $selectedTypes,
                'ai_question_types'
            );
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'errors' => Arr::flatten($e->errors()),
            ], 422);
        }

        // Essay is manually graded — never let AI generate essays in batch
        // mode regardless of what the pool allows or the form submitted.
        $aiTypes = array_values(array_filter($aiTypes, static fn ($t) => $t !== 'essay'));
        if ($aiTypes === []) {
            return response()->json([
                'ok' => false,
                'errors' => [__('AI generation needs at least one auto-gradable question type (MCQ, True/False, or Fill-in-the-blank). Essays are manually graded and must be added by hand.')],
            ], 422);
        }

        $batchCount = (int) $validated['batch_count'];
        $batchIndex = (int) ($validated['batch_index'] ?? 0);
        $totalCount = (int) ($validated['total_count'] ?? $batchCount);

        // Per-type breakdown for this batch (optional). When present, every
        // count must reference an auto-gradable type the user has selected,
        // sum to batch_count, and never include essay.
        $typeCounts = [];
        if (isset($validated['ai_type_counts']) && is_array($validated['ai_type_counts'])) {
            foreach ($validated['ai_type_counts'] as $t => $n) {
                $tn = is_string($t) ? strtolower(trim($t)) : '';
                $ni = (int) $n;
                if ($tn === '' || $ni <= 0) {
                    continue;
                }
                if (! in_array($tn, $aiTypes, true)) {
                    return response()->json([
                        'ok' => false,
                        'errors' => [__('ai_type_counts contains a type that is not enabled for AI generation: :type.', ['type' => $tn])],
                    ], 422);
                }
                $typeCounts[$tn] = $ni;
            }
            if ($typeCounts !== [] && array_sum($typeCounts) !== $batchCount) {
                return response()->json([
                    'ok' => false,
                    'errors' => [__('ai_type_counts must sum to batch_count (:sum vs :batch).', [
                        'sum' => (string) array_sum($typeCounts),
                        'batch' => (string) $batchCount,
                    ])],
                ], 422);
            }
        }

        $existing = array_values(array_filter(array_map(
            static fn ($t) => is_string($t) ? mb_strtolower(trim($t)) : '',
            $validated['existing_question_texts'] ?? []
        ), static fn ($t) => $t !== ''));

        // Nudge the model to keep batches distinct so we don't waste calls on
        // near-duplicates across runs (the validator dedupes too, but a model
        // hint is cheaper than throwing batches away).
        $batchHint = '';
        if ($totalCount > $batchCount) {
            $batchHint = sprintf(
                "\n\nThis is batch %d of an overall set of %d questions. Produce %d NEW questions that do not repeat or paraphrase any earlier batch. Vary subtopics and difficulty within the allowed range.",
                $batchIndex + 1,
                (int) ceil($totalCount / max(1, $batchCount)),
                $batchCount,
            );
        }

        $prompt = $promptBuilder->build([
            'topic' => $combinedTopic.$batchHint,
            'count' => $batchCount,
            'types' => $aiTypes,
            'type_counts' => $typeCounts,
            'difficulty' => (string) ($validated['ai_difficulty'] ?? 'mixed'),
            'marks_per_question' => (float) ($validated['ai_marks'] ?? 1),
        ]);

        // Retry transient provider failures (timeouts, 429, 5xx) so a single
        // hiccup mid-prep doesn't kill the whole run. Total worst-case latency
        // per batch ≈ initial_call + 1s + retry + 2s + retry = roughly
        // (3 × per-call timeout) + 3s — still much shorter than what one big
        // 250-question single-shot would have taken.
        $gen = $this->runAiBatchWithRetry(
            $aiGenerator,
            $prompt,
            $selectedTypes,
            $existing !== [] ? $existing : null,
        );
        if (! $gen['ok']) {
            return response()->json([
                'ok' => false,
                'errors' => $gen['errors'],
            ], 422);
        }

        try {
            $typeProbe = new Quiz(['selected_question_types' => $selectedTypes]);
            AssessmentQuestionTypes::assertSectionsOnlyUseAllowedTypes($typeProbe, $gen['sections'], 'ai_question_types');
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'errors' => Arr::flatten($e->errors()),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'sections' => $gen['sections'],
        ]);
    }

    /**
     * Run a single batch generation, retrying transient provider failures
     * (timeouts, connection issues, 429, 5xx) before giving up.
     *
     * @param  list<string>       $allowedTypes
     * @param  list<string>|null  $existing
     * @return array{ok: true, sections: list<array<string, mixed>>}|array{ok: false, errors: list<string>}
     */
    private function runAiBatchWithRetry(
        ExamAiQuestionGenerator $aiGenerator,
        string $prompt,
        array $allowedTypes,
        ?array $existing,
        int $maxAttempts = 3,
    ): array {
        $attempt = 0;
        $last = ['ok' => false, 'errors' => ['AI generation failed.']];

        while ($attempt < max(1, $maxAttempts)) {
            $attempt++;
            // lenient=true: the validator silently drops duplicates and
            // malformed individual questions instead of failing the whole
            // batch — the browser loop just asks for more in subsequent
            // batches until the lecturer's total is met.
            $last = $aiGenerator->generateFromPrompt($prompt, $allowedTypes, $existing, true);
            if ($last['ok'] === true) {
                return $last;
            }
            if (! $this->isTransientAiFailure($last['errors'] ?? [])) {
                return $last;
            }
            if ($attempt < $maxAttempts) {
                // 1s, 2s — short enough that the user's progress bar barely
                // pauses, long enough to ride out provider rate-limit windows.
                Sleep::sleep($attempt);
            }
        }

        return $last;
    }

    /**
     * Decide whether the last LLM error is the kind we should retry. We avoid
     * retrying schema/auth/config issues — those will fail the same way every
     * time and just waste the user's time.
     *
     * @param  list<string>  $errors
     */
    private function isTransientAiFailure(array $errors): bool
    {
        foreach ($errors as $msg) {
            $m = mb_strtolower((string) $msg);
            if (
                str_contains($m, 'timed out')
                || str_contains($m, 'could not connect')
                || str_contains($m, 'before reaching provider')
                || str_contains($m, 'temporarily unavailable')
                || str_contains($m, 'rate-limited')
                || str_contains($m, ', 429,')
                || str_contains($m, ', 500,')
                || str_contains($m, ', 502,')
                || str_contains($m, ', 503,')
                || str_contains($m, ', 504,')
            ) {
                return true;
            }
        }

        return false;
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

        $assessmentTypeIn = (string) $request->input('assessment_type');
        $creatingAssignment = $assessmentTypeIn === 'assignment';

        $validated = $request->validate([
            'wizard_step' => ['nullable', 'integer', 'in:1,2'],
            'course_id' => ['required', 'integer'],
            'classroom_ids' => ['required', 'array', 'min:1'],
            'classroom_ids.*' => ['integer', 'distinct'],
            'title' => ['required', 'string', 'max:255'],
            'description' => $creatingAssignment
                ? ['required', 'string', 'min:10', 'max:20000']
                : ['nullable', 'string', 'max:20000'],
            'duration_minutes' => $creatingAssignment
                ? ['nullable', 'integer', 'min:0', 'max:20160']
                : ['required', 'integer', 'min:1', 'max:600'],
            'assessment_type' => ['required', 'string', 'in:quiz,mid,exam,assignment'],
            'selected_question_types' => $creatingAssignment
                ? ['nullable', 'array']
                : ['required', 'array', 'min:1'],
            'selected_question_types.*' => ['string', 'in:mcq,true_false,fill_blank,essay'],
            'questions_per_student' => ['nullable', 'integer', 'min:1', 'max:500'],
            'randomize_questions' => ['sometimes', 'boolean'],
            'randomize_options' => ['sometimes', 'boolean'],
            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date', 'after_or_equal:start_time'],
            'due_at' => $creatingAssignment ? ['required', 'date'] : ['nullable', 'date'],
            'assignment_question' => $creatingAssignment
                ? ['required', 'string', 'min:10', 'max:50000']
                : ['nullable', 'string', 'max:50000'],
            'assignment_marks' => $creatingAssignment
                ? ['required', 'numeric', 'min:1', 'max:10000']
                : ['nullable', 'numeric', 'min:1', 'max:10000'],
            'question_source' => $creatingAssignment
                ? ['nullable', 'string', 'in:later']
                : ['required', 'string', 'in:later,paste_json,ai_generate'],
            'import_json' => ['nullable', 'string', 'max:500000'],
            'paste_prompt_topics' => ['nullable', 'string', 'max:4000'],
            'paste_prompt_count' => ['nullable', 'integer', 'min:1', 'max:250'],
            'ai_topics' => ['nullable', 'string', 'max:4000'],
            'ai_question_count' => ['nullable', 'integer', 'min:1', 'max:250'],
            'ai_question_types' => ['nullable', 'array'],
            'ai_question_types.*' => ['string', 'in:mcq,true_false,fill_blank,essay'],
            'ai_difficulty' => ['nullable', 'string', 'max:120'],
            'ai_marks' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'ai_outline_file' => ['nullable', 'file', 'max:5120', 'mimes:txt,pdf,docx,csv'],
            // Optional JSON payload produced by the in-browser batched AI prep
            // flow. When present we skip the synchronous LLM call below.
            'ai_pregenerated_sections' => ['nullable', 'string', 'max:2000000'],
            'activate_now' => ['sometimes', 'boolean'],
            'show_correct_answers_in_results' => ['sometimes', 'boolean'],
            'enable_phone' => ['sometimes', 'boolean'],
            'enable_fullscreen' => ['sometimes', 'boolean'],
            'enable_auto_submit' => ['sometimes', 'boolean'],
        ], [
            'classroom_ids.required' => __('Select at least one class group for the chosen course.'),
            'classroom_ids.min' => __('Select at least one class group for the chosen course.'),
        ], [
            'classroom_ids' => __('class groups'),
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

        $source = $creatingAssignment ? 'later' : $validated['question_source'];
        $selectedTypes = $creatingAssignment
            ? ['essay']
            : AssessmentQuestionTypes::normalizeFromRequest($request->input('selected_question_types'));

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

            $hasPregenerated = trim((string) $request->input('ai_pregenerated_sections', '')) !== '';

            $topicsPart = trim((string) $request->input('ai_topics', ''));
            $outlineText = '';
            if ($request->hasFile('ai_outline_file')) {
                $outlineText = app(ExamAssessmentDocumentTextExtractor::class)->extractPlainText($request->file('ai_outline_file'));
            }
            if (! $hasPregenerated && $topicsPart === '' && $outlineText === '') {
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

        $assessmentType = $validated['assessment_type'];
        $initialProctoringSettings = AssessmentProctoringDefaults::baselineForType(
            $assessmentType,
            $allowPhone,
            $allowFullscreen,
            $allowAutoSubmit,
        );
        $initialProctoringSettings['show_correct_answers_to_students'] = $request->boolean('show_correct_answers_in_results');

        if ($assessmentType !== 'assignment') {
            $normalizedDraft = ProctoringOrchestratorService::normalizeProctoringSettings($initialProctoringSettings, null);
            $normalizedDraft['phone_detection_enabled'] = $allowPhone ? $request->boolean('enable_phone', true) : false;
            $normalizedDraft['fullscreen_enforced'] = $allowFullscreen ? $request->boolean('enable_fullscreen', true) : false;
            $normalizedDraft['auto_submit_enabled'] = $allowAutoSubmit ? $request->boolean('enable_auto_submit', true) : false;
            $initialProctoringSettings = $normalizedDraft;
        }

        $dueAt = ! empty($validated['due_at'] ?? null)
            ? Carbon::parse((string) $validated['due_at'])
            : null;

        $start = isset($validated['start_time']) ? Carbon::parse($validated['start_time']) : null;
        $end = isset($validated['end_time']) ? Carbon::parse($validated['end_time']) : null;

        $pendingImportSections = null;
        if ($source === 'paste_json') {
            $result = $importValidator->validateJsonString((string) $request->input('import_json', ''), $selectedTypes, null);
            if (! $result['ok']) {
                return back()
                    ->withErrors(['import_json' => implode("\n", $result['errors'])])
                    ->withInput();
            }
            $typeProbe = new Quiz(['selected_question_types' => $selectedTypes]);
            AssessmentQuestionTypes::assertSectionsOnlyUseAllowedTypes($typeProbe, $result['sections']);
            $pendingImportSections = $result['sections'];
        } elseif ($source === 'ai_generate') {
            // Preferred path: the browser already streamed batches via
            // aiGenerateBatch and is handing us the assembled sections. This
            // avoids the one-shot timeout when many questions are requested.
            $pregenerated = trim((string) $request->input('ai_pregenerated_sections', ''));
            if ($pregenerated !== '') {
                $result = $importValidator->validateJsonString($pregenerated, $selectedTypes, null);
                if (! $result['ok']) {
                    return back()
                        ->withErrors(['ai_topics' => implode("\n", $result['errors'])])
                        ->withInput();
                }
                $typeProbe = new Quiz(['selected_question_types' => $selectedTypes]);
                AssessmentQuestionTypes::assertSectionsOnlyUseAllowedTypes($typeProbe, $result['sections'], 'ai_topics');
                $pendingImportSections = $result['sections'];
            } else {
                $topic = (string) $combinedAiTopic;
                $aiTypes = AssessmentQuestionTypes::intersectAiTypesWithAllowed(
                    $request->input('ai_question_types'),
                    $selectedTypes,
                    'ai_question_types'
                );
                $prompt = $promptBuilder->build([
                    'topic' => $topic,
                    'count' => (int) $request->input('ai_question_count'),
                    'types' => $aiTypes,
                    'difficulty' => $request->input('ai_difficulty') ?? 'mixed',
                    'marks_per_question' => (float) ($request->input('ai_marks') ?? 1),
                ]);
                $gen = $aiGenerator->generateFromPrompt($prompt, $selectedTypes, null);
                if (! $gen['ok']) {
                    return back()
                        ->withErrors(['ai_topics' => implode("\n", $gen['errors'])])
                        ->withInput();
                }
                $typeProbe = new Quiz(['selected_question_types' => $selectedTypes]);
                AssessmentQuestionTypes::assertSectionsOnlyUseAllowedTypes($typeProbe, $gen['sections'], 'ai_topics');
                $pendingImportSections = $gen['sections'];
            }
        }

        $quiz = Quiz::create(array_merge([
            'university_id' => $user->university_id,
            'academic_year_id' => $year->id,
            'term_id' => $activeTerm?->id,
            'course_id' => $courseId,
            'created_by' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'assessment_type' => $validated['assessment_type'],
            'selected_question_types' => $selectedTypes,
            'status' => 'draft',
            'duration_minutes' => $creatingAssignment ? 0 : (int) $validated['duration_minutes'],
            'total_marks' => 0,
            'questions_per_student' => isset($validated['questions_per_student']) ? (int) $validated['questions_per_student'] : null,
            'randomize_questions' => $request->boolean('randomize_questions'),
            'randomize_options' => $request->boolean('randomize_options'),
            'proctoring_settings' => $initialProctoringSettings,
            'start_time' => $start,
            'end_time' => $end,
            'due_at' => $dueAt,
        ], $creatingAssignment ? [
            'assignment_allows_text' => true,
            'assignment_allows_files' => true,
            'assignment_attachment_required' => false,
            'assignment_disable_paste' => true,
            'assignment_allowed_extensions' => ['pdf', 'docx', 'txt'],
            'assignment_max_file_kb' => 5120,
        ] : []));

        $quiz->targetClassrooms()->sync($classIds);

        if ($creatingAssignment) {
            $this->bootstrapAssignmentEssayQuestion(
                $quiz,
                (string) $validated['assignment_question'],
                (float) $validated['assignment_marks'],
            );
        }

        $importErrors = null;
        if ($pendingImportSections !== null) {
            $this->persistImportedSections($quiz, $pendingImportSections);
            $importErrors = $this->validateQuestionsPerStudentAgainstPool($quiz, (int) $request->input('questions_per_student'));
        }

        if ($importErrors !== null) {
            $quiz->delete();

            return back()->withErrors(['questions_per_student' => $importErrors])->withInput();
        }

        $quiz->refresh();
        $this->bumpExamConfigCache($quiz);

        $status = __('Assessment saved. Review and approve the question pool to continue.');

        // For non-assignments, the freshly-imported questions are in draft
        // status and need approval before they can be delivered. Send the
        // examiner to the dedicated review-pool screen so they can approve
        // (or reject) before reaching the full workspace.
        $hasDraftPool = ! $creatingAssignment
            && Question::query()
                ->where('quiz_id', $quiz->id)
                ->where('pool_status', 'draft')
                ->exists();

        if ($request->boolean('activate_now')) {
            try {
                $lifecycle->publish($quiz->fresh());
                $status = __('Assessment created and published for the selected class groups.');

                // Publish succeeded → questions are approved → go to workspace.
                return redirect()
                    ->route('examiner.quizzes.workspace', $quiz)
                    ->with('status', $status);
            } catch (ValidationException $e) {
                // Publish failed (typically because nothing is approved yet).
                // Send the user to the review page where they can approve and
                // then publish from the workspace.
                if ($hasDraftPool) {
                    return redirect()
                        ->route('examiner.exams.review', $quiz)
                        ->withErrors($e->errors())
                        ->with('status', __('Saved as draft — approve questions below, then publish from the workspace when ready.'));
                }

                return redirect()
                    ->route('examiner.quizzes.workspace', $quiz)
                    ->withErrors($e->errors())
                    ->with('status', __('Saved as draft — fix the items below, then publish from the builder when ready.'));
            }
        }

        if ($hasDraftPool) {
            return redirect()
                ->route('examiner.exams.review', $quiz)
                ->with('status', $status);
        }

        return redirect()
            ->route('examiner.quizzes.workspace', $quiz)
            ->with('status', __('Assessment saved. Continue in the builder.'));
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

    /**
     * @return list<string>|null error messages or null if ok
     */
    private function validateQuestionsPerStudentAgainstPool(Quiz $exam, int $perStudent): ?array
    {
        $poolCount = Question::query()
            ->where('quiz_id', $exam->id)
            ->where('pool_status', '!=', 'archived')
            ->count();

        if ($poolCount < 1) {
            return [__('Add at least one question before setting delivery.')];
        }

        if ($perStudent > $poolCount) {
            return [__('Questions per student cannot exceed the number of questions in the pool (:count).', ['count' => $poolCount])];
        }

        return null;
    }

    public function builder(Request $request, Quiz $exam, SystemSettingsService $systemSettings, AssessmentAnalyticsService $analytics): View
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

        $shareUrl = route('student.exam.prepare', $exam, absolute: true);
        if (filled($exam->share_token)) {
            $displayToken = (string) $exam->share_token;
        } else {
            $tokenSeed = hash('sha256', (string) config('app.key').':'.$exam->id);
            $displayToken = strtoupper(substr($tokenSeed, 0, 8)).'-'.strtoupper(substr($tokenSeed, 8, 8));
        }

        $mobileOnly = (bool) data_get($exam->proctoring_settings, 'mobile_only', false);

        $overviewQuestions = $this->overviewQuestionRows($exam);

        // Rich per-question performance + summary tiles for the analytics
        // tab. We compute these once (cheap aggregate) so the workspace can
        // render the table + bar/donut chart inline without an extra page.
        $questionPerfRows = $analytics->questionPerformance($exam);
        $analyticsCohort = $analytics->cohortOverview($exam);
        $analyticsHeader = [
            'sessions' => (int) ($analyticsCohort['submitted'] ?? 0),
            'attempts' => 0,
            'correct' => 0,
            'wrong' => 0,
            'total_marks' => 0.0,
        ];
        foreach ($questionPerfRows as $row) {
            $analyticsHeader['attempts'] += (int) ($row['answered'] ?? 0);
            $analyticsHeader['correct'] += (int) ($row['correct'] ?? 0);
            $analyticsHeader['wrong'] += (int) ($row['wrong'] ?? 0);
            $analyticsHeader['total_marks'] += (float) ($row['marks'] ?? 0);
        }

        // Keep the legacy "counts by type" rollup as a small extra so the
        // tab still shows it even when no sessions have been submitted yet.
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

        // URLs for the Scores & Export tab. Just the action links —
        // the heavy stats live in the Question analytics tab.
        $scoresPayload = null;
        if ($request->user()->can('manageResults', $exam)) {
            $scoresPayload = [
                'preview_pdf_url' => route('examiner.exams.score-report', $exam),
                'download_pdf_url' => route('examiner.exams.score-report', ['exam' => $exam, 'download' => 1]),
                'export_csv_url' => route('examiner.exams.sessions.export-csv', $exam),
                'class_summary_url' => route('examiner.exams.classes.summary', $exam),
            ];
        }

        $assignmentWorkspaceStats = null;
        if ($exam->isAssignment()) {
            $submittedBase = ExamSession::query()
                ->where('exam_id', $exam->id)
                ->where('status', 'submitted');
            $assignmentWorkspaceStats = [
                'submitted_sessions' => (clone $submittedBase)->count(),
                'late_submissions' => (clone $submittedBase)->where('submitted_late', true)->count(),
                'pending_manual' => Result::query()->where('quiz_id', $exam->id)->where('status', 'pending_manual')->count(),
                'graded' => Result::query()->where('quiz_id', $exam->id)->where('status', 'graded')->count(),
                'held' => Result::query()->where('quiz_id', $exam->id)->where('status', 'held')->count(),
            ];
        }

        $allowedWorkspaceTabs = ['overview', 'sessions', 'scores', 'analytics'];
        if ($exam->isAssignment()) {
            $allowedWorkspaceTabs[] = 'settings';
        }
        $workspaceTab = (string) $request->query('tab', 'overview');
        if (! in_array($workspaceTab, $allowedWorkspaceTabs, true)) {
            $workspaceTab = 'overview';
        }
        if ($workspaceTab === 'sessions' && $sessionsWorkspace === null) {
            $workspaceTab = 'overview';
        }
        if ($workspaceTab === 'settings' && ! $exam->isAssignment()) {
            $workspaceTab = 'overview';
        }

        return view('examiner.exams.builder', [
            'exam' => $exam,
            'questionTypes' => ['mcq', 'true_false', 'fill_blank', 'essay'],
            'allowedQuestionTypes' => AssessmentQuestionTypes::effective($exam->selected_question_types),
            'aiEnabled' => $systemSettings->getBool('enable_ai', true),
            'importPreview' => is_array($importPreview) ? $importPreview : null,
            'canEditContent' => $exam->status === 'draft',
            'canEditSchedule' => $exam->status === 'draft',
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
            'questionPerfRows' => $questionPerfRows,
            'analyticsHeader' => $analyticsHeader,
            'sessionsWorkspace' => $sessionsWorkspace,
            'scoresPayload' => $scoresPayload,
            'workspaceTab' => $workspaceTab,
            'assignmentWorkspaceStats' => $assignmentWorkspaceStats,
        ]);
    }

    /**
     * Intermediate step shown after the create wizard completes, BEFORE the
     * full workspace. Lets the examiner review the generated question pool
     * and approve / reject items, then continue to the workspace.
     *
     * For assignments (essay-only, single-question) the review step is
     * irrelevant — we redirect to the workspace immediately. Same for
     * quizzes that have no pool yet (source=later).
     */
    public function reviewPool(Request $request, Quiz $exam, SystemSettingsService $systemSettings): View|RedirectResponse
    {
        $this->authorize('view', $exam);

        if ($exam->isAssignment()) {
            return redirect()->route('examiner.quizzes.workspace', $exam);
        }

        $exam->load([
            'course:id,code,title',
            'sections' => fn ($q) => $q->orderBy('section_order'),
            'sections.questions' => fn ($q) => $q->orderBy('question_order'),
        ]);

        $overviewQuestions = $this->overviewQuestionRows($exam);

        // Nothing to review yet — skip straight to workspace.
        if ($overviewQuestions === []) {
            return redirect()
                ->route('examiner.quizzes.workspace', $exam)
                ->with('status', __('No questions to review yet — add some from the workspace below.'));
        }

        $poolQuestionTotal = Question::query()->where('quiz_id', $exam->id)->count();
        $poolApprovedCount = Question::query()->where('quiz_id', $exam->id)->where('pool_status', 'approved')->count();

        $importPreview = session('exam_question_import_'.$exam->id);

        return view('examiner.exams.review-pool', [
            'exam' => $exam,
            'allowedQuestionTypes' => AssessmentQuestionTypes::effective($exam->selected_question_types),
            'aiEnabled' => $systemSettings->getBool('enable_ai', true),
            'importPreview' => is_array($importPreview) ? $importPreview : null,
            'canEditContent' => $exam->status === 'draft',
            'canEditPool' => $exam->status !== 'archived',
            'poolQuestionTotal' => $poolQuestionTotal,
            'poolApprovedCount' => $poolApprovedCount,
            'generationLocked' => $poolQuestionTotal > 0,
            'overviewQuestions' => $overviewQuestions,
        ]);
    }

    public function gradeAssignmentWithAi(Request $request, Quiz $exam, AssignmentEssayAiGradingService $aiGrading): RedirectResponse
    {
        $this->authorize('update', $exam);
        abort_unless($exam->isAssignment(), 404);

        $result = $aiGrading->gradePendingForExam($exam, $request->user());

        $message = __('AI assist graded :n submission(s).', ['n' => $result['graded']]);
        if ($result['skipped'] > 0) {
            $message .= ' '.__(':skipped could not be graded.', ['skipped' => $result['skipped']]);
        }
        if ($result['graded'] > 0) {
            // Tack on a hint so the examiner knows the rows have moved out
            // of the "pending" queue into "drafted by AI — confirm to release".
            $message .= ' '.__('Review the drafts below and confirm to release.');
        }

        // When the form was submitted from the grading queue, send the user
        // BACK to the queue so they can see the success flash + a panel
        // listing the just-AI-graded submissions (the rows themselves move
        // out of "pending" so without this redirect + panel it looks like
        // nothing happened). The default redirect (workspace) is preserved
        // for callers from the assignment workspace surface.
        $returnTo = strtolower((string) $request->input('return_to', ''));
        $redirect = $returnTo === 'pending'
            ? redirect()->route('examiner.grading.pending', ['exam' => $exam->id])
            : redirect()->route('examiner.quizzes.workspace', ['exam' => $exam, 'tab' => 'overview']);

        return $redirect
            ->with('status', $message)
            ->with('ai_grade_errors', $result['errors'])
            ->with('ai_grade_just_completed', [
                'exam_id' => (int) $exam->id,
                'exam_title' => (string) $exam->title,
                'answer_ids' => $result['graded_answer_ids'],
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

                $mcqOptions = null;
                $mcqCorrectIndices = [];
                if ($q->isMCQ() && is_array($q->options)) {
                    $mcqOptions = array_values($q->options);
                    $ca = $q->correct_answer;
                    if (is_array($ca)) {
                        foreach ($ca as $v) {
                            if (is_int($v) || (is_string($v) && ctype_digit($v))) {
                                $mcqCorrectIndices[] = (int) $v;
                            }
                        }
                    } elseif (is_int($ca) || (is_string($ca) && ctype_digit($ca))) {
                        $mcqCorrectIndices[] = (int) $ca;
                    }
                    $mcqCorrectIndices = array_values(array_unique($mcqCorrectIndices));
                }

                $rows[] = [
                    'id' => $q->id,
                    'n' => $n,
                    'text' => $q->question_text,
                    'type' => $q->type,
                    'typeLabel' => strtoupper(str_replace('_', ' ', $q->type)),
                    'pool_status' => $q->pool_status,
                    'topic' => $topic,
                    'ai' => $aiFlag,
                    'marks' => (float) $q->marks,
                    'answer' => $this->questionAnswerPreviewLine($q),
                    'section' => $section->title,
                    'options' => $mcqOptions,
                    'correct_indices' => $mcqOptions !== null ? $mcqCorrectIndices : [],
                    'marking_guide' => trim((string) data_get($q->metadata, 'marking_guide', '')),
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

        $extras = array_intersect_key($current, array_flip(['show_correct_answers_to_students', 'mobile_only', 'require_essay_marking_guide_on_publish', 'assignment_clipboard_block', 'late_acceptance_hours']));
        $merged = array_merge($normalized, $extras);
        if ($exam->isAssignment()) {
            $merged = AssessmentProctoringDefaults::enforceAssignmentCaps($merged);
        }

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

        if ($validated['pool_status'] === 'approved') {
            AssessmentQuestionTypes::assertQuestionTypeAllowedForQuiz($exam, (string) $question->type, 'pool_status');
        }

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

        if ($validated['pool_status'] === 'approved') {
            $allowed = AssessmentQuestionTypes::effective($exam->selected_question_types);
            $bad = (clone $query)->whereNotIn('type', $allowed)->exists();
            if ($bad) {
                throw ValidationException::withMessages([
                    'pool_status' => [__('One or more selected questions use a type that is not enabled for this assessment. Change allowed types or leave those questions as draft.')],
                ]);
            }
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

    public function updateSelectedQuestionTypes(Request $request, Quiz $exam): RedirectResponse
    {
        $this->authorize('update', $exam);
        $this->assertExamDraftForContentMutations($exam);

        $validated = $request->validate([
            'selected_question_types' => ['required', 'array', 'min:1'],
            'selected_question_types.*' => ['string', 'in:mcq,true_false,fill_blank,essay'],
        ]);

        $next = AssessmentQuestionTypes::normalizeFromRequest($validated['selected_question_types']);

        $used = Question::query()
            ->where('quiz_id', $exam->id)
            ->where('pool_status', '!=', 'archived')
            ->distinct()
            ->pluck('type')
            ->map(fn ($t) => strtolower((string) $t))
            ->filter()
            ->values()
            ->all();

        foreach ($used as $ut) {
            if (! in_array($ut, $next, true)) {
                throw ValidationException::withMessages([
                    'selected_question_types' => [__('Cannot remove question type :type while the pool still contains active (non-archived) questions of that type. Archive those questions first.', ['type' => $ut])],
                ]);
            }
        }

        $exam->update(['selected_question_types' => $next]);

        $this->bumpExamConfigCache($exam->fresh());

        return back()->with('status', __('Allowed question types updated.'));
    }

    public function publish(Request $request, Quiz $exam, ExamLifecycleService $lifecycle): RedirectResponse
    {
        $this->authorize('update', $exam);

        $lifecycle->publish($exam->fresh());

        return back()->with('status', __('Quiz published. Eligible students can now see and start it on their dashboard within your availability window.'));
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

    public function destroy(Request $request, Quiz $exam): RedirectResponse
    {
        $this->authorize('delete', $exam);

        $title = (string) $exam->title;

        // FK cascades drop sections, questions, exam_sessions, answers,
        // results, and assignment files; proctoring_events.quiz_id is
        // nullable and gets nulled. Pivot rows (quiz_class) are removed
        // alongside the quiz row by the DB.
        $exam->delete();

        return redirect()
            ->route('examiner.exams.index')
            ->with('status', __('Assessment ":title" deleted.', ['title' => $title]));
    }

    /**
     * Score report PDF: preview inline or download an official report
     * with university header, class group meta, and a per-student
     * score / violation table.
     *
     * Held results are displayed as "On hold – see lecturer" instead
     * of the raw number so the printout matches the institution's
     * "results held" policy.
     */
    public function scoreReport(Request $request, Quiz $exam): Response
    {
        $this->authorize('manageResults', $exam);

        $exam->loadMissing(['course', 'creator', 'university', 'targetClassrooms']);

        $sessions = ExamSession::query()
            ->where('exam_id', $exam->id)
            ->where('status', 'submitted')
            ->with('student:id,name,index_number')
            ->orderBy('end_time')
            ->orderBy('id')
            ->get();

        $studentIds = $sessions->pluck('student_id')->unique()->values();
        $resultsByStudent = Result::query()
            ->where('quiz_id', $exam->id)
            ->whereIn('user_id', $studentIds)
            ->get(['id', 'user_id', 'quiz_id', 'score', 'status'])
            ->keyBy('user_id');

        $totalMarks = max(1, (int) round((float) $exam->total_marks));

        $rows = $sessions
            ->map(function (ExamSession $session) use ($resultsByStudent, $totalMarks): ?array {
                $student = $session->student;
                if ($student === null) {
                    return null;
                }
                $result = $resultsByStudent->get($student->id);
                $status = (string) ($result?->status ?? 'graded');
                $isHeld = $status === 'held'
                    || in_array((string) $session->exam_status, ['submitted_held', 'locked_by_admin'], true);

                return [
                    'index_number' => $student->index_number ?? '—',
                    'name' => $student->name ?? '—',
                    'mark' => $isHeld
                        ? null
                        : (int) round((float) ($result->score ?? 0)),
                    'is_held' => $isHeld,
                    'violation' => $this->primaryViolationLabel($session),
                ];
            })
            ->filter()
            ->values();

        // Sort: highest scores first, then held rows last for a clean printout.
        $rows = $rows->sortBy(function (array $row): array {
            return [$row['is_held'] ? 1 : 0, -1 * (int) ($row['mark'] ?? 0)];
        })->values();

        $classGroupLabels = $exam->targetClassrooms
            ->map(fn (Classroom $c) => trim($c->name))
            ->filter()
            ->values()
            ->all();
        $classGroupLine = $classGroupLabels === []
            ? '—'
            : implode(' • ', $classGroupLabels);
        $reportTitle = $classGroupLabels === []
            ? (string) $exam->title
            : implode(', ', $classGroupLabels);

        $payload = [
            'exam' => $exam,
            'rows' => $rows,
            'totalMarks' => $totalMarks,
            'classGroupLine' => $classGroupLine,
            'reportTitle' => $reportTitle,
            'universityName' => $exam->university?->name ?? config('app.name'),
            'lecturerName' => $exam->creator?->name ?? '—',
            'courseLine' => $exam->course
                ? trim(($exam->course->code ?? '').' '.($exam->course->title ?? ''))
                : '—',
            'examName' => (string) $exam->title,
            'generatedAt' => now(),
            'studentCount' => $rows->count(),
        ];

        $pdf = Pdf::loadView('examiner.exams.score-report', $payload)
            ->setPaper('a4', 'portrait');

        $filename = 'score-report-'.$exam->id.'-'.now()->format('Ymd-His').'.pdf';

        $response = $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);

        // Hint the browser's inline PDF viewer to show our favicon
        // in the tab. Without this it would fall back to the default
        // (which often appears blank or as a generic page icon).
        $response->headers->set(
            'Link',
            '<'.asset('favicon.svg').'>; rel="icon"; type="image/svg+xml"',
        );

        return $response;
    }

    /**
     * Pick a single human-friendly violation label for the printable
     * report. Only auto-submission triggers are surfaced — informational
     * proctoring logs (face missing, fullscreen exits, etc. that did not
     * stop the session) are intentionally omitted from the official PDF.
     */
    private function primaryViolationLabel(ExamSession $session): string
    {
        $code = (string) ($session->auto_submit_reason_code ?? '');
        if ($code === '') {
            return '—';
        }

        return match ($code) {
            'phone_detected' => 'Phone detected',
            'multiple_faces_limit' => 'Multiple faces',
            'tab_switch_limit' => 'Tab switching',
            'screenshot_attempt' => 'Screenshot attempt',
            'screen_record_attempt' => 'Screen recording',
            'violation_threshold' => 'Violation threshold',
            'force_submit' => 'Force-submitted by invigilator',
            default => '—',
        };
    }

    public function updateSchedule(Request $request, Quiz $exam): RedirectResponse
    {
        $this->authorize('update', $exam);
        $this->assertExamDraftForSchedule($exam);

        $validated = $request->validate([
            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
        ]);

        $start = isset($validated['start_time']) ? Carbon::parse($validated['start_time']) : null;
        $end = isset($validated['end_time']) ? Carbon::parse($validated['end_time']) : null;

        if ($start !== null && $end !== null && $end->lt($start)) {
            return back()->withErrors(['end_time' => 'End time must be on or after start time.'])->withInput();
        }

        $payload = [
            'start_time' => $start,
            'end_time' => $end,
        ];

        if (array_key_exists('due_at', $validated)) {
            $payload['due_at'] = $validated['due_at'] !== null && $validated['due_at'] !== ''
                ? Carbon::parse($validated['due_at'])
                : null;
        }
        if (array_key_exists('title', $validated) && $validated['title'] !== null) {
            $payload['title'] = $validated['title'];
        }
        if (array_key_exists('description', $validated)) {
            $payload['description'] = $validated['description'];
        }

        $exam->update($payload);

        $this->bumpExamConfigCache($exam->fresh());

        return back()->with('status', 'Exam window updated.');
    }

    public function updateAssignmentSubmissionSettings(Request $request, Quiz $exam): RedirectResponse
    {
        $this->authorize('update', $exam);
        abort_unless($exam->isAssignment(), 404);

        $validated = $request->validate([
            'assignment_allowed_extensions' => ['nullable', 'string', 'max:500'],
            'assignment_max_file_kb' => ['nullable', 'integer', 'min:256', 'max:51200'],
        ]);

        if (! $request->boolean('assignment_allows_text') && ! $request->boolean('assignment_allows_files')) {
            throw ValidationException::withMessages([
                'assignment_allows_text' => [__('Choose at least one of typed text or file upload.')],
            ]);
        }

        $allowsText = $request->boolean('assignment_allows_text');
        $allowsFiles = $request->boolean('assignment_allows_files');
        $attachmentRequired = $allowsFiles && $request->boolean('assignment_attachment_required');
        $disablePaste = $allowsText && $request->boolean('assignment_disable_paste');
        $allowCode = $allowsText && $request->boolean('assignment_allow_code');

        if (! $allowsFiles) {
            $attachmentRequired = false;
        }

        if (! $allowsText && $allowsFiles) {
            $attachmentRequired = true;
        }

        $rawExt = trim((string) ($validated['assignment_allowed_extensions'] ?? ''));
        $extArr = $rawExt === ''
            ? (array) ($exam->assignment_allowed_extensions ?? ['pdf', 'docx', 'txt'])
            : array_values(array_unique(array_filter(array_map(
                static fn (string $s): string => strtolower(ltrim(trim($s), '.')),
                preg_split('/[\s,]+/', $rawExt) ?: [],
            ))));

        $exam->update([
            'assignment_allows_text' => $allowsText,
            'assignment_allows_files' => $allowsFiles,
            'assignment_attachment_required' => $attachmentRequired,
            'assignment_disable_paste' => $disablePaste,
            'assignment_allow_code' => $allowCode,
            'assignment_allowed_extensions' => $extArr,
            'assignment_max_file_kb' => (int) ($validated['assignment_max_file_kb'] ?? ($exam->assignment_max_file_kb ?? 5120)),
        ]);

        $this->bumpExamConfigCache($exam->fresh());

        return back()->with('status', __('Assignment submission options updated.'));
    }

    public function releaseAssignmentGrades(Request $request, Quiz $exam): RedirectResponse
    {
        $this->authorize('update', $exam);
        abort_unless($exam->status === 'published', 403);
        abort_unless($exam->isAssignment(), 404);

        $exam->update(['grades_released_at' => now()]);

        ActivityLog::query()->create([
            'user_id' => $request->user()->id,
            'quiz_id' => $exam->id,
            'event_type' => 'assignment_grades_released',
            'event_data' => [
                'released_at' => now()->toIso8601String(),
            ],
            'created_at' => now(),
        ]);

        $this->bumpExamConfigCache($exam->fresh());

        return back()->with('status', __('Students can now see grades and feedback for this assignment.'));
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
        AssessmentQuestionTypes::assertQuestionTypeAllowedForQuiz($exam, $type);

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

        $allowed = AssessmentQuestionTypes::effective($exam->selected_question_types);
        $result = $validator->validateJsonString(
            $validated['import_json'],
            $allowed,
            $this->normalizedPoolQuestionFingerprints($exam)
        );
        if (! $result['ok']) {
            return back()
                ->withErrors(['import_json' => implode("\n", $result['errors'])])
                ->withInput();
        }

        AssessmentQuestionTypes::assertSectionsOnlyUseAllowedTypes($exam, $result['sections']);

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

        AssessmentQuestionTypes::assertSectionsOnlyUseAllowedTypes($exam, $bundle['sections']);

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
            'ai_topic' => ['required', 'string', 'max:4000'],
            'ai_count' => ['required', 'integer', 'min:1', 'max:250'],
            'ai_question_types' => ['nullable', 'array'],
            'ai_question_types.*' => ['string', 'in:mcq,true_false,fill_blank,essay'],
            'ai_difficulty' => ['nullable', 'string', 'max:120'],
            'ai_marks' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);

        $allowed = AssessmentQuestionTypes::effective($exam->selected_question_types);
        $types = AssessmentQuestionTypes::intersectAiTypesWithAllowed(
            $validated['ai_question_types'] ?? null,
            $allowed,
            'ai_question_types'
        );

        $prompt = $promptBuilder->build([
            'topic' => $this->normalizeAiTopicWireForPrompt((string) $validated['ai_topic']),
            'count' => (int) $validated['ai_count'],
            'types' => $types,
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
            'ai_topic' => ['required_without:ai_custom_prompt', 'nullable', 'string', 'max:4000'],
            'ai_count' => ['required_without:ai_custom_prompt', 'nullable', 'integer', 'min:1', 'max:250'],
            'ai_question_types' => ['nullable', 'array'],
            'ai_question_types.*' => ['string', 'in:mcq,true_false,fill_blank,essay'],
            'ai_difficulty' => ['nullable', 'string', 'max:120'],
            'ai_marks' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);

        $custom = trim((string) ($validated['ai_custom_prompt'] ?? ''));
        if ($custom !== '') {
            $prompt = $custom;
        } else {
            $allowed = AssessmentQuestionTypes::effective($exam->selected_question_types);
            $types = AssessmentQuestionTypes::intersectAiTypesWithAllowed(
                $validated['ai_question_types'] ?? null,
                $allowed,
                'ai_question_types'
            );
            $prompt = app(ExamAiPromptBuilder::class)->build([
                'topic' => $this->normalizeAiTopicWireForPrompt((string) ($validated['ai_topic'] ?? '')),
                'count' => (int) ($validated['ai_count'] ?? 5),
                'types' => $types,
                'difficulty' => $validated['ai_difficulty'] ?? 'mixed',
                'marks_per_question' => (float) ($validated['ai_marks'] ?? 1),
            ]);
        }

        $result = $generator->generateFromPrompt(
            $prompt,
            AssessmentQuestionTypes::effective($exam->selected_question_types),
            $this->normalizedPoolQuestionFingerprints($exam)
        );
        if (! $result['ok']) {
            return back()->withErrors(['ai' => implode("\n", $result['errors'])])->withInput();
        }

        AssessmentQuestionTypes::assertSectionsOnlyUseAllowedTypes($exam, $result['sections'], 'ai');

        session()->put('exam_question_import_'.$exam->id, [
            'sections' => $result['sections'],
            'source' => 'ai',
        ]);

        return back()->with('status', 'AI draft validated — review preview below before saving.');
    }

    /**
     * @return list<string>
     */
    private function normalizedPoolQuestionFingerprints(Quiz $exam): array
    {
        return Question::query()
            ->where('quiz_id', $exam->id)
            ->where('pool_status', '!=', 'archived')
            ->pluck('question_text')
            ->map(fn ($t) => mb_strtolower(trim((string) $t)))
            ->filter(fn ($t) => $t !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<array{title: string, questions: list<array<string, mixed>>}>  $sections
     */
    private function persistImportedSections(Quiz $exam, array $sections): void
    {
        AssessmentQuestionTypes::assertSectionsOnlyUseAllowedTypes($exam, $sections);

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
                        'metadata' => $q['metadata'] ?? null,
                        'pool_status' => 'draft',
                    ]);
                }
            }

            $total = (float) Question::query()->where('quiz_id', $exam->id)->sum('marks');
            $exam->update(['total_marks' => $total]);
        });
    }

    /**
     * Topics from the builder may be JSON-encoded tag arrays (commas-safe) or a legacy plain string.
     */
    private function normalizeAiTopicWireForPrompt(string $wire): string
    {
        $wire = trim($wire);
        if ($wire === '') {
            return '';
        }
        if (str_starts_with($wire, '[')) {
            $decoded = json_decode($wire, true);
            if (is_array($decoded) && array_is_list($decoded)) {
                $parts = [];
                foreach ($decoded as $item) {
                    if (is_string($item) && trim($item) !== '') {
                        $parts[] = trim($item);
                    }
                }

                return implode("\n", $parts);
            }
        }

        return $wire;
    }

    private function bootstrapAssignmentEssayQuestion(Quiz $exam, string $questionText, float $marks): void
    {
        $questionText = trim($questionText);
        $marks = max(1.0, $marks);

        DB::transaction(function () use ($exam, $questionText, $marks): void {
            $section = ExamSection::query()->create([
                'exam_id' => $exam->id,
                'title' => __('Assignment'),
                'section_order' => 1,
            ]);

            Question::query()->create([
                'quiz_id' => $exam->id,
                'section_id' => $section->id,
                'question_text' => $questionText,
                'type' => 'essay',
                'options' => null,
                'correct_answer' => null,
                'answer_schema' => null,
                'marks' => $marks,
                'question_order' => 1,
                'pool_status' => 'approved',
            ]);

            $exam->update([
                'total_marks' => $marks,
                'questions_per_student' => 1,
            ]);
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
