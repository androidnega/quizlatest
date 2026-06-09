<?php

namespace App\Support;

use App\Models\ExamSession;
use App\Models\ExamSessionQuestion;
use App\Models\Question;
use App\Models\Quiz;
use App\Services\ProctoringOrchestratorService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Student-safe exam structure + timing + saved answers for GET .../state (authoritative with resolver base).
 */
final class ExamRuntimeStateExtension
{
    /**
     * Architecture Review Phase 1 — slim volatile-only state payload.
     *
     * Returns ONLY the fields that change between polls (timer values,
     * pause flags, overlay state, tab-switch counter, proctoring client
     * hints, etc.). Excludes sections, questions, options, saved
     * answers, and exam metadata — those live in /exam-structure
     * (browser-cacheable, ETagged) and /answers (revision-aware,
     * ETagged) respectively.
     *
     * @return array<string, mixed>
     */
    public static function volatileStateFor(ExamSession $examSession): array
    {
        $examSession->loadMissing(['exam']);
        $exam = $examSession->exam;
        $now = now();

        if ($exam === null) {
            return [
                'server_time' => $now->toAtomString(),
                'exam_end_at' => null,
                'time_remaining_seconds' => 0,
                'duration_minutes' => 0,
                'timer_paused' => false,
                'timer_pause_reason' => null,
                'proctoring_overlay' => self::proctoringOverlayPayload($examSession),
                'tab_switch_count' => 0,
                'proctoring_client' => null,
            ];
        }

        $durationMinutes = (int) ($exam->duration_minutes ?? 0);
        $timeRemainingSeconds = ExamSessionTimer::timeRemainingSeconds($examSession, $exam, $now);
        $examEndAtIso = ExamSessionTimer::examEndAtIso($examSession, $exam, $now);
        $timerPaused = ExamSessionTimer::timerPaused($examSession);

        return [
            'server_time' => $now->toAtomString(),
            'exam_end_at' => $examEndAtIso,
            'time_remaining_seconds' => $timeRemainingSeconds,
            'duration_minutes' => $durationMinutes,
            'timer_paused' => $timerPaused,
            'timer_pause_reason' => $timerPaused ? 'disconnect' : null,
            'assessment_type' => (string) ($exam->assessment_type ?? 'exam'),
            'due_at' => $exam->due_at?->toAtomString(),
            'submitted_late' => (bool) ($examSession->submitted_late ?? false),
            'proctoring_overlay' => self::proctoringOverlayPayload($examSession),
            'tab_switch_count' => (int) ($examSession->tab_switch_count ?? 0),
            'proctoring_client' => self::studentProctoringClientHints($exam),
        ];
    }

    /**
     * Architecture Review Phase 1 — invariant exam structure payload.
     *
     * Returns sections + questions + options + answer_schema. This data
     * does NOT change for the life of an exam attempt (option order is
     * stable per session via ExamSessionQuestion.option_order JSON).
     * Browser-cacheable, ETag-able.
     *
     * @return array<string, mixed>
     */
    public static function structureFor(ExamSession $examSession): array
    {
        $examSession->loadMissing([
            'exam.course',
            'sessionQuestions.question',
        ]);

        /** @var Quiz|null $exam */
        $exam = $examSession->exam;
        if ($exam === null) {
            return [
                'exam' => null,
                'sections' => [],
            ];
        }

        $assignedMarks = 0.0;
        $sectionsPayload = [];

        if ($examSession->sessionQuestions->isEmpty()) {
            // Legacy fallback: an attempt that pre-dates the
            // session-questions assignment gets the full exam tree.
            $exam->load([
                'sections' => fn ($q) => $q->orderBy('section_order'),
                'sections.questions' => fn ($q) => $q->orderBy('question_order'),
            ]);

            foreach ($exam->sections as $section) {
                $sectionsPayload[] = [
                    'id' => $section->id,
                    'title' => $section->title,
                    'section_order' => (int) $section->section_order,
                    'questions' => $section->questions->map(
                        fn (Question $q) => self::serializeQuestionForStudent($q, null),
                    )->values()->all(),
                ];
            }

            $orphans = $exam->questions()
                ->whereNull('section_id')
                ->orderBy('question_order')
                ->get();
            if ($orphans->isNotEmpty()) {
                $sectionsPayload[] = [
                    'id' => null,
                    'title' => 'Questions',
                    'section_order' => 999_999,
                    'questions' => $orphans->map(fn (Question $q) => self::serializeQuestionForStudent($q, null))->values()->all(),
                ];
            }

            $totalMarks = $exam->total_marks !== null ? (float) $exam->total_marks : null;
        } else {
            /** @var Collection<int, ExamSessionQuestion> $ordered */
            $ordered = $examSession->sessionQuestions->sortBy('display_order')->values();

            if ($exam->randomize_questions) {
                $questionsPayload = [];
                foreach ($ordered as $sq) {
                    $q = $sq->question;
                    if ($q === null) {
                        continue;
                    }
                    $assignedMarks += (float) $q->marks;
                    $questionsPayload[] = self::serializeQuestionForStudent($q, $sq->mcqDisplayToOriginal());
                }

                $sectionsPayload[] = [
                    'id' => null,
                    'title' => 'Questions',
                    'section_order' => 1,
                    'questions' => $questionsPayload,
                ];
            } else {
                $exam->loadMissing([
                    'sections' => fn ($q) => $q->orderBy('section_order'),
                ]);

                $sectionsById = $exam->sections->keyBy('id');
                $grouped = [];

                foreach ($ordered as $sq) {
                    $q = $sq->question;
                    if ($q === null) {
                        continue;
                    }
                    $assignedMarks += (float) $q->marks;
                    $sectionKey = $q->section_id !== null ? (string) $q->section_id : '_orphan';
                    $grouped[$sectionKey] ??= [];
                    $grouped[$sectionKey][] = ['sq' => $sq, 'q' => $q];
                }

                foreach ($grouped as $sectionKey => $items) {
                    $sectionModel = is_numeric($sectionKey) ? $sectionsById->get((int) $sectionKey) : null;
                    $title = $sectionModel?->title ?? 'Questions';
                    $sectionOrder = $sectionModel !== null ? (int) $sectionModel->section_order : 999_999;

                    $questionsPayload = [];
                    foreach ($items as $item) {
                        /** @var Question $question */
                        $question = $item['q'];
                        /** @var ExamSessionQuestion $link */
                        $link = $item['sq'];
                        $questionsPayload[] = self::serializeQuestionForStudent($question, $link->mcqDisplayToOriginal());
                    }

                    $sectionsPayload[] = [
                        'id' => $sectionModel?->id,
                        'title' => $title,
                        'section_order' => $sectionOrder,
                        'questions' => $questionsPayload,
                    ];
                }

                usort($sectionsPayload, fn ($a, $b) => $a['section_order'] <=> $b['section_order']);
            }

            $totalMarks = round($assignedMarks, 2);
        }

        return [
            'exam' => [
                'id' => (int) $exam->id,
                'title' => (string) $exam->title,
                'description' => $exam->description,
                'duration_minutes' => (int) ($exam->duration_minutes ?? 0),
                'total_marks' => $totalMarks,
                'assessment_type' => (string) ($exam->assessment_type ?? 'exam'),
                'due_at' => $exam->due_at?->toAtomString(),
                'start_time' => $exam->start_time?->toAtomString(),
                'end_time' => $exam->end_time?->toAtomString(),
                'grades_released_at' => $exam->grades_released_at?->toAtomString(),
                'course' => self::coursePayload($exam),
                'assignment_allows_text' => (bool) ($exam->assignment_allows_text ?? true),
                'assignment_allows_files' => (bool) ($exam->assignment_allows_files ?? false),
                'assignment_attachment_required' => (bool) ($exam->assignment_attachment_required ?? false),
                'assignment_disable_paste' => (bool) ($exam->assignment_disable_paste ?? true),
                'assignment_allowed_extensions' => $exam->assignment_allowed_extensions,
                'assignment_max_file_kb' => $exam->assignment_max_file_kb,
            ],
            'sections' => $sectionsPayload,
        ];
    }

    /**
     * Architecture Review Phase 1 — answers map, revision-aware.
     *
     * @return array{
     *     saved_answers: array<string, array<string, mixed>>,
     *     revision: int
     * }
     */
    public static function answersFor(ExamSession $examSession): array
    {
        $examSession->loadMissing('answers');
        $map = self::buildSavedAnswersMap($examSession);

        // The "revision" is the max client_revision across all answers
        // for this session, plus the count. This gives ETag a stable
        // value that monotonically advances on every save.
        $maxRevision = 0;
        foreach ($examSession->answers as $answer) {
            $r = (int) ($answer->client_revision ?? 0);
            if ($r > $maxRevision) {
                $maxRevision = $r;
            }
        }

        return [
            'saved_answers' => $map,
            'revision' => $maxRevision,
            'answer_count' => $examSession->answers->count(),
        ];
    }

    /**
     * Stable ETag for /exam-structure. Invariant for the life of an
     * exam attempt unless the examiner edits the source quiz mid-window
     * (rare; we capture exam.updated_at so the ETag flips correctly).
     */
    public static function structureEtag(ExamSession $examSession): string
    {
        $examSession->loadMissing('exam');
        $exam = $examSession->exam;
        if ($exam === null) {
            return 'es:'.$examSession->id;
        }

        $bits = [
            'es:'.$examSession->id,
            'q:'.$exam->id,
            'qu:'.($exam->updated_at?->getTimestamp() ?? 0),
            'sc:'.($examSession->created_at?->getTimestamp() ?? 0),
        ];

        return '"'.substr(sha1(implode('|', $bits)), 0, 16).'"';
    }

    /**
     * ETag for /answers — flips on every saved answer or count change.
     */
    public static function answersEtag(ExamSession $examSession): string
    {
        $examSession->loadMissing('answers');
        $maxRevision = 0;
        $maxUpdatedTs = 0;
        foreach ($examSession->answers as $answer) {
            $r = (int) ($answer->client_revision ?? 0);
            if ($r > $maxRevision) {
                $maxRevision = $r;
            }
            $u = $answer->updated_at?->getTimestamp() ?? 0;
            if ($u > $maxUpdatedTs) {
                $maxUpdatedTs = $u;
            }
        }
        $count = $examSession->answers->count();

        return '"a-'.substr(sha1("es:{$examSession->id}|c:{$count}|r:{$maxRevision}|t:{$maxUpdatedTs}"), 0, 16).'"';
    }

    /**
     * @return array<string, mixed>
     */
    public static function forSession(ExamSession $examSession): array
    {
        // Audit P2.9: a single loadMissing() with all relations the entire
        // payload needs. Was previously: one loadMissing() at top, another
        // for course, then two more for sections / sections.questions in
        // the legacy branch. Total reduction: up to 5 SELECTs per request
        // collapsed into the minimum needed below (sections are only
        // loaded when sessionQuestions is empty).
        $examSession->loadMissing([
            'exam.course',
            'answers',
            'sessionQuestions.question',
        ]);

        /** @var Quiz|null $exam */
        $exam = $examSession->exam;
        if ($exam === null) {
            return self::emptyRuntime();
        }

        $now = now();
        $durationMinutes = (int) ($exam->duration_minutes ?? 0);
        $start = $examSession->start_time;

        $timeRemainingSeconds = ExamSessionTimer::timeRemainingSeconds($examSession, $exam, $now);
        $examEndAtIso = ExamSessionTimer::examEndAtIso($examSession, $exam, $now);
        $timerPaused = ExamSessionTimer::timerPaused($examSession);

        if ($examSession->sessionQuestions->isEmpty()) {
            return array_merge(
                self::legacyFullExamPayload($examSession, $exam, $now, $examEndAtIso, $timeRemainingSeconds, $durationMinutes),
                [
                    'timer_paused' => $timerPaused,
                    'timer_pause_reason' => $timerPaused ? 'disconnect' : null,
                    'proctoring_overlay' => self::proctoringOverlayPayload($examSession),
                    'tab_switch_count' => (int) ($examSession->tab_switch_count ?? 0),
                    'proctoring_client' => self::studentProctoringClientHints($exam),
                ],
            );
        }

        /** @var Collection<int, ExamSessionQuestion> $ordered */
        $ordered = $examSession->sessionQuestions->sortBy('display_order')->values();

        $assignedMarks = 0.0;
        $sectionsPayload = [];

        if ($exam->randomize_questions) {
            // Single flat block in display_order so cross-section randomization is visible and stable per session.
            $questionsPayload = [];
            foreach ($ordered as $sq) {
                $q = $sq->question;
                if ($q === null) {
                    continue;
                }
                $assignedMarks += (float) $q->marks;
                $questionsPayload[] = self::serializeQuestionForStudent($q, $sq->mcqDisplayToOriginal());
            }

            $sectionsPayload[] = [
                'id' => null,
                'title' => 'Questions',
                'section_order' => 1,
                'questions' => $questionsPayload,
            ];
        } else {
            $exam->loadMissing([
                'sections' => fn ($q) => $q->orderBy('section_order'),
            ]);

            $sectionsById = $exam->sections->keyBy('id');

            $grouped = [];
            foreach ($ordered as $sq) {
                $q = $sq->question;
                if ($q === null) {
                    continue;
                }
                $assignedMarks += (float) $q->marks;
                $sectionKey = $q->section_id !== null ? (string) $q->section_id : '_orphan';
                $grouped[$sectionKey] ??= [];
                $grouped[$sectionKey][] = ['sq' => $sq, 'q' => $q];
            }

            foreach ($grouped as $sectionKey => $items) {
                $sectionModel = is_numeric($sectionKey) ? $sectionsById->get((int) $sectionKey) : null;
                $title = $sectionModel?->title ?? 'Questions';
                $sectionOrder = $sectionModel !== null ? (int) $sectionModel->section_order : 999_999;

                $questionsPayload = [];
                foreach ($items as $item) {
                    /** @var Question $question */
                    $question = $item['q'];
                    /** @var ExamSessionQuestion $link */
                    $link = $item['sq'];
                    $questionsPayload[] = self::serializeQuestionForStudent($question, $link->mcqDisplayToOriginal());
                }

                $sectionsPayload[] = [
                    'id' => $sectionModel?->id,
                    'title' => $title,
                    'section_order' => $sectionOrder,
                    'questions' => $questionsPayload,
                ];
            }

            usort($sectionsPayload, fn ($a, $b) => $a['section_order'] <=> $b['section_order']);
        }

        $savedAnswers = self::buildSavedAnswersMap($examSession);

        return [
            'server_time' => $now->toAtomString(),
            'exam_end_at' => $examEndAtIso,
            'time_remaining_seconds' => $timeRemainingSeconds,
            'duration_minutes' => $durationMinutes,
            'timer_paused' => $timerPaused,
            'timer_pause_reason' => $timerPaused ? 'disconnect' : null,
            'assessment_type' => (string) ($exam->assessment_type ?? 'exam'),
            'due_at' => $exam->due_at?->toAtomString(),
            'submitted_late' => (bool) ($examSession->submitted_late ?? false),
            'proctoring_overlay' => self::proctoringOverlayPayload($examSession),
            'tab_switch_count' => (int) ($examSession->tab_switch_count ?? 0),
            'proctoring_client' => self::studentProctoringClientHints($exam),
            'exam' => [
                'id' => (int) $exam->id,
                'title' => (string) $exam->title,
                'description' => $exam->description,
                'duration_minutes' => $durationMinutes,
                'total_marks' => round($assignedMarks, 2),
                'assessment_type' => (string) ($exam->assessment_type ?? 'exam'),
                'due_at' => $exam->due_at?->toAtomString(),
                'start_time' => $exam->start_time?->toAtomString(),
                'end_time' => $exam->end_time?->toAtomString(),
                'grades_released_at' => $exam->grades_released_at?->toAtomString(),
                'course' => self::coursePayload($exam),
                'assignment_allows_text' => (bool) ($exam->assignment_allows_text ?? true),
                'assignment_allows_files' => (bool) ($exam->assignment_allows_files ?? false),
                'assignment_attachment_required' => (bool) ($exam->assignment_attachment_required ?? false),
                'assignment_disable_paste' => (bool) ($exam->assignment_disable_paste ?? true),
                'assignment_allowed_extensions' => $exam->assignment_allowed_extensions,
                'assignment_max_file_kb' => $exam->assignment_max_file_kb,
            ],
            'sections' => $sectionsPayload,
            'saved_answers' => $savedAnswers,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function legacyFullExamPayload(
        ExamSession $examSession,
        Quiz $exam,
        Carbon $now,
        ?string $examEndAtIso,
        int $timeRemainingSeconds,
        int $durationMinutes,
    ): array {
        $exam->load([
            'course',
            'sections' => fn ($q) => $q->orderBy('section_order'),
            'sections.questions' => fn ($q) => $q->orderBy('question_order'),
        ]);

        $sectionsPayload = [];
        foreach ($exam->sections as $section) {
            $sectionsPayload[] = [
                'id' => $section->id,
                'title' => $section->title,
                'section_order' => (int) $section->section_order,
                'questions' => $section->questions->map(fn (Question $q) => self::serializeQuestionForStudent($q, null))->values()->all(),
            ];
        }

        $orphans = $exam->questions()
            ->whereNull('section_id')
            ->orderBy('question_order')
            ->get();

        if ($orphans->isNotEmpty()) {
            $sectionsPayload[] = [
                'id' => null,
                'title' => 'Questions',
                'section_order' => 999_999,
                'questions' => $orphans->map(fn (Question $q) => self::serializeQuestionForStudent($q, null))->values()->all(),
            ];
        }

        return [
            'server_time' => $now->toAtomString(),
            'exam_end_at' => $examEndAtIso,
            'time_remaining_seconds' => $timeRemainingSeconds,
            'duration_minutes' => $durationMinutes,
            'assessment_type' => (string) ($exam->assessment_type ?? 'exam'),
            'due_at' => $exam->due_at?->toAtomString(),
            'submitted_late' => (bool) ($examSession->submitted_late ?? false),
            'proctoring_overlay' => self::proctoringOverlayPayload($examSession),
            'tab_switch_count' => (int) ($examSession->tab_switch_count ?? 0),
            'proctoring_client' => self::studentProctoringClientHints($exam),
            'exam' => [
                'id' => (int) $exam->id,
                'title' => (string) $exam->title,
                'description' => $exam->description,
                'duration_minutes' => $durationMinutes,
                'total_marks' => $exam->total_marks !== null ? (float) $exam->total_marks : null,
                'assessment_type' => (string) ($exam->assessment_type ?? 'exam'),
                'due_at' => $exam->due_at?->toAtomString(),
                'start_time' => $exam->start_time?->toAtomString(),
                'end_time' => $exam->end_time?->toAtomString(),
                'grades_released_at' => $exam->grades_released_at?->toAtomString(),
                'course' => self::coursePayload($exam),
                'assignment_allows_text' => (bool) ($exam->assignment_allows_text ?? true),
                'assignment_allows_files' => (bool) ($exam->assignment_allows_files ?? false),
                'assignment_attachment_required' => (bool) ($exam->assignment_attachment_required ?? false),
                'assignment_disable_paste' => (bool) ($exam->assignment_disable_paste ?? true),
                'assignment_allowed_extensions' => $exam->assignment_allowed_extensions,
                'assignment_max_file_kb' => $exam->assignment_max_file_kb,
            ],
            'sections' => $sectionsPayload,
            'saved_answers' => self::buildSavedAnswersMap($examSession),
        ];
    }

    /**
     * @return array{code: string, title: string}|null
     */
    private static function coursePayload(Quiz $exam): ?array
    {
        $course = $exam->course;
        if ($course === null) {
            return null;
        }

        return [
            'code' => (string) ($course->code ?? ''),
            'title' => (string) ($course->title ?? ''),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function buildSavedAnswersMap(ExamSession $examSession): array
    {
        $savedAnswers = [];
        foreach ($examSession->answers as $answer) {
            $savedAnswers[(string) $answer->question_id] = [
                'answer_payload' => $answer->answer_payload,
                'saved_at' => $answer->saved_at?->toAtomString(),
                'client_revision' => (int) ($answer->client_revision ?? 0),
            ];
        }

        return $savedAnswers;
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyRuntime(): array
    {
        return [
            'server_time' => now()->toAtomString(),
            'exam_end_at' => null,
            'time_remaining_seconds' => 0,
            'duration_minutes' => 0,
            'timer_paused' => false,
            'timer_pause_reason' => null,
            'exam' => null,
            'sections' => [],
            'saved_answers' => [],
            'proctoring_overlay' => ['active' => false, 'reason' => null, 'message' => null],
            'tab_switch_count' => 0,
            'proctoring_client' => null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function studentProctoringClientHints(?Quiz $exam): ?array
    {
        if ($exam === null) {
            return null;
        }

        $normalized = ProctoringOrchestratorService::normalizeProctoringSettings(
            is_array($exam->proctoring_settings) ? $exam->proctoring_settings : [],
            $exam->id,
        );

        return [
            'phone_detection_confidence_threshold' => (float) data_get($normalized, 'phone_detection_confidence_threshold', 0.55),
            'screenshot_autosubmit_enabled' => (bool) ($normalized['screenshot_autosubmit_enabled'] ?? false),
            'external_display_detection_enabled' => (bool) ($normalized['external_display_detection_enabled'] ?? true),
        ];
    }

    /**
     * @return array{active: bool, reason: ?string, message: ?string}
     */
    private static function proctoringOverlayPayload(ExamSession $examSession): array
    {
        $active = (bool) ($examSession->proctoring_blur_active ?? false);
        $reason = $examSession->proctoring_blur_reason;
        $message = match ((string) $reason) {
            'external_display' => 'External display risk detected. Disconnect it to continue.',
            'face_obstruction' => 'Your face must stay clearly visible. Adjust your camera, then continue when ready.',
            'camera_lost' => 'Camera access was lost. Restore camera access to continue.',
            default => $active ? 'Please resolve the issue shown above to continue.' : null,
        };

        return [
            'active' => $active,
            'reason' => $reason !== null && $reason !== '' ? (string) $reason : null,
            'message' => $message,
        ];
    }

    /**
     * @param  list<int>|null  $mcqDisplayToOriginal
     * @return array<string, mixed>
     */
    private static function serializeQuestionForStudent(Question $question, ?array $mcqDisplayToOriginal): array
    {
        $base = [
            'id' => (int) $question->id,
            'section_id' => $question->section_id !== null ? (int) $question->section_id : null,
            'type' => (string) $question->type,
            'question_text' => (string) $question->question_text,
            'marks' => (float) $question->marks,
            'question_order' => (int) $question->question_order,
            'answer_schema' => is_array($question->answer_schema) ? $question->answer_schema : null,
        ];

        if ($question->type === 'mcq') {
            $opts = is_array($question->options) ? $question->options : [];
            if ($mcqDisplayToOriginal !== null && $mcqDisplayToOriginal !== []) {
                $shuffled = [];
                foreach ($mcqDisplayToOriginal as $origIdx) {
                    $shuffled[] = $opts[$origIdx] ?? '';
                }
                $base['options'] = $shuffled;
            } else {
                $base['options'] = $opts;
            }
        }

        return $base;
    }
}
