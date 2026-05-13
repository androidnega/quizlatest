<?php

namespace App\Support;

use App\Models\ExamSession;
use App\Models\ExamSessionQuestion;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Student-safe exam structure + timing + saved answers for GET .../state (authoritative with resolver base).
 */
final class ExamRuntimeStateExtension
{
    /**
     * @return array<string, mixed>
     */
    public static function forSession(ExamSession $examSession): array
    {
        $examSession->loadMissing(['exam', 'answers', 'sessionQuestions.question']);

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
            ],
            'sections' => $sectionsPayload,
            'saved_answers' => self::buildSavedAnswersMap($examSession),
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
