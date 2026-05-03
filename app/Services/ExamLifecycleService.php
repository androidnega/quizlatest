<?php

namespace App\Services;

use App\Models\ExamSection;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Draft / published / archived transitions and publish validation (no proctoring/runtime changes).
 */
final class ExamLifecycleService
{
    public function __construct(
        private readonly ExamRedisService $examRedis,
    ) {}

    /**
     * @return list<string>
     */
    public function publishValidationErrors(Quiz $exam): array
    {
        $errors = [];

        $exam->loadCount(['sections', 'questions']);

        if ($exam->sections_count < 1) {
            $errors[] = 'Add at least one section before publishing.';
        }

        if ($exam->questions_count < 1) {
            $errors[] = 'Add at least one question before publishing.';
        }

        if ((float) $exam->total_marks <= 0) {
            $errors[] = 'Total marks must be greater than zero.';
        }

        $badMarks = Question::query()
            ->where('quiz_id', $exam->id)
            ->where(function ($q): void {
                $q->whereNull('marks')->orWhere('marks', '<=', 0);
            })
            ->exists();

        if ($badMarks) {
            $errors[] = 'Every question must have marks greater than zero.';
        }

        return $errors;
    }

    public function publish(Quiz $exam): void
    {
        $errors = $this->publishValidationErrors($exam);
        if ($errors !== []) {
            throw ValidationException::withMessages(['lifecycle' => $errors]);
        }

        if ($exam->status !== 'draft') {
            throw ValidationException::withMessages([
                'lifecycle' => ['Only draft exams can be published.'],
            ]);
        }

        $exam->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->examRedis->forgetExamConfig((int) $exam->id);
    }

    public function unpublish(Quiz $exam): void
    {
        if ($exam->status !== 'published') {
            throw ValidationException::withMessages([
                'lifecycle' => ['Only published exams can be unpublished.'],
            ]);
        }

        $exam->update([
            'status' => 'draft',
            'published_at' => null,
        ]);

        $this->examRedis->forgetExamConfig((int) $exam->id);
    }

    public function archive(Quiz $exam): void
    {
        if ($exam->status === 'archived') {
            throw ValidationException::withMessages([
                'lifecycle' => ['Exam is already archived.'],
            ]);
        }

        if (! in_array($exam->status, ['draft', 'published'], true)) {
            throw ValidationException::withMessages([
                'lifecycle' => ['Invalid status for archive.'],
            ]);
        }

        $exam->update([
            'status' => 'archived',
        ]);

        $this->examRedis->forgetExamConfig((int) $exam->id);
    }

    /**
     * Duplicate exam as a new draft (sections + questions).
     */
    public function cloneToDraft(Quiz $source, int $creatorUserId, int $universityId): Quiz
    {
        return DB::transaction(function () use ($source, $creatorUserId, $universityId): Quiz {
            $source->load([
                'sections' => fn ($q) => $q->orderBy('section_order'),
                'sections.questions' => fn ($q) => $q->orderBy('question_order'),
            ]);

            $copy = Quiz::query()->create([
                'university_id' => $universityId,
                'course_id' => $source->course_id,
                'created_by' => $creatorUserId,
                'title' => $this->uniqueCloneTitle($source),
                'description' => $source->description,
                'assessment_type' => $source->assessment_type,
                'status' => 'draft',
                'published_at' => null,
                'duration_minutes' => $source->duration_minutes,
                'total_marks' => 0,
                'proctoring_settings' => $source->proctoring_settings,
                'start_time' => null,
                'end_time' => null,
            ]);

            $totalMarks = 0.0;

            foreach ($source->sections as $sec) {
                $newSec = ExamSection::query()->create([
                    'exam_id' => $copy->id,
                    'title' => $sec->title,
                    'section_order' => $sec->section_order,
                ]);

                foreach ($sec->questions as $q) {
                    Question::query()->create([
                        'quiz_id' => $copy->id,
                        'section_id' => $newSec->id,
                        'question_text' => $q->question_text,
                        'type' => $q->type,
                        'options' => $q->options,
                        'correct_answer' => $q->correct_answer,
                        'answer_schema' => $q->answer_schema,
                        'marks' => $q->marks,
                        'question_order' => $q->question_order,
                    ]);
                    $totalMarks += (float) $q->marks;
                }
            }

            $copy->update(['total_marks' => $totalMarks]);

            return $copy->fresh();
        });
    }

    private function uniqueCloneTitle(Quiz $source): string
    {
        $stem = $source->title.' (copy)';
        $candidate = $stem;
        $i = 2;
        while (Quiz::query()->where('course_id', $source->course_id)->where('title', $candidate)->exists()) {
            $candidate = $stem.' '.$i;
            $i++;
        }

        return $candidate;
    }
}
