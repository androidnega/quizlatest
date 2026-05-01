<?php

namespace App\Support;

use App\Models\ExamSession;
use App\Models\Question;
use App\Models\Quiz;

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
        $examSession->loadMissing(['exam', 'answers']);

        /** @var Quiz|null $exam */
        $exam = $examSession->exam;
        if ($exam === null) {
            return self::emptyRuntime();
        }

        $exam->load([
            'sections' => fn ($q) => $q->orderBy('section_order'),
            'sections.questions' => fn ($q) => $q->orderBy('question_order'),
        ]);

        $now = now();
        $durationMinutes = (int) ($exam->duration_minutes ?? 0);
        $start = $examSession->start_time;

        $timeRemainingSeconds = 0;
        if ($examSession->status !== 'submitted' && $start !== null && $durationMinutes > 0) {
            $endAt = $start->copy()->addMinutes($durationMinutes);
            $timeRemainingSeconds = max(0, $endAt->getTimestamp() - $now->getTimestamp());
        }

        $sectionsPayload = [];
        foreach ($exam->sections as $section) {
            $sectionsPayload[] = [
                'id' => $section->id,
                'title' => $section->title,
                'section_order' => (int) $section->section_order,
                'questions' => $section->questions->map(fn (Question $q) => self::serializeQuestionForStudent($q))->values()->all(),
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
                'questions' => $orphans->map(fn (Question $q) => self::serializeQuestionForStudent($q))->values()->all(),
            ];
        }

        $savedAnswers = [];
        foreach ($examSession->answers as $answer) {
            $savedAnswers[(string) $answer->question_id] = [
                'answer_payload' => $answer->answer_payload,
                'saved_at' => $answer->saved_at?->toAtomString(),
            ];
        }

        return [
            'server_time' => $now->toAtomString(),
            'time_remaining_seconds' => $timeRemainingSeconds,
            'duration_minutes' => $durationMinutes,
            'exam' => [
                'id' => (int) $exam->id,
                'title' => (string) $exam->title,
                'description' => $exam->description,
                'duration_minutes' => $durationMinutes,
                'total_marks' => $exam->total_marks !== null ? (float) $exam->total_marks : null,
            ],
            'sections' => $sectionsPayload,
            'saved_answers' => $savedAnswers,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyRuntime(): array
    {
        return [
            'server_time' => now()->toAtomString(),
            'time_remaining_seconds' => 0,
            'duration_minutes' => 0,
            'exam' => null,
            'sections' => [],
            'saved_answers' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function serializeQuestionForStudent(Question $question): array
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
            $base['options'] = is_array($question->options) ? $question->options : [];
        }

        return $base;
    }
}
