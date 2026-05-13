<?php

namespace App\Services;

use App\Models\ExamSection;
use App\Models\Question;
use App\Models\Quiz;
use App\Support\AssessmentQuestionTypes;
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

        $exam->loadCount(['sections']);

        if ($exam->sections_count < 1) {
            $errors[] = 'Add at least one section before publishing.';
        }

        $allowedTypes = AssessmentQuestionTypes::effective($exam->selected_question_types);
        if ($allowedTypes === []) {
            $errors[] = 'Select at least one question type for this assessment before publishing.';
        }

        $approvedCount = Question::query()
            ->where('quiz_id', $exam->id)
            ->where('pool_status', 'approved')
            ->count();

        if ($approvedCount < 1) {
            $errors[] = 'At least one approved question is required in the pool.';
        }

        $perStudent = $exam->questions_per_student;
        if ($perStudent === null || (int) $perStudent < 1) {
            $errors[] = 'Set how many questions each student answers (questions per student).';
        } elseif ((int) $perStudent > $approvedCount) {
            $errors[] = 'Questions per student cannot exceed the number of approved questions.';
        }

        $badApprovedMarks = Question::query()
            ->where('quiz_id', $exam->id)
            ->where('pool_status', 'approved')
            ->where(function ($q): void {
                $q->whereNull('marks')->orWhere('marks', '<=', 0);
            })
            ->exists();

        if ($badApprovedMarks) {
            $errors[] = 'Every approved question must have marks greater than zero.';
        }

        $approvedMarksSum = (float) Question::query()
            ->where('quiz_id', $exam->id)
            ->where('pool_status', 'approved')
            ->sum('marks');

        if ($approvedMarksSum <= 0) {
            $errors[] = 'Approved questions must have a positive total mark value.';
        }

        if ($allowedTypes !== [] && $approvedCount > 0) {
            $badApprovedType = Question::query()
                ->where('quiz_id', $exam->id)
                ->where('pool_status', 'approved')
                ->whereNotIn('type', $allowedTypes)
                ->exists();

            if ($badApprovedType) {
                $errors[] = 'The approved pool contains a question type that is not enabled for this assessment. Archive those questions or widen the allowed question types.';
            }
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
                'selected_question_types' => $source->selected_question_types,
                'status' => 'draft',
                'published_at' => null,
                'duration_minutes' => $source->duration_minutes,
                'total_marks' => 0,
                'questions_per_student' => $source->questions_per_student,
                'randomize_questions' => (bool) ($source->randomize_questions ?? false),
                'randomize_options' => (bool) ($source->randomize_options ?? false),
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
                        'pool_status' => $q->pool_status ?? 'draft',
                    ]);
                    $totalMarks += (float) $q->marks;
                }
            }

            $copy->update(['total_marks' => $totalMarks]);

            $classIds = $source->targetClassrooms()->pluck('id')->all();
            if ($classIds !== []) {
                $copy->targetClassrooms()->sync($classIds);
            }

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
