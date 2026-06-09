<?php

namespace App\Services;

use App\Models\ExamSection;
use App\Models\Question;
use App\Models\Quiz;
use App\Support\AssessmentProctoringDefaults;
use App\Support\AssessmentQuestionTypes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Draft / published / archived transitions and publish validation (no proctoring/runtime changes).
 */
final class ExamLifecycleService
{
    public function __construct(
        private readonly ExamRuntimeService $examRuntime,
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
            $errors[] = 'This assessment has no selected question types.';
        }

        $approvedCount = Question::query()
            ->where('quiz_id', $exam->id)
            ->where('pool_status', 'approved')
            ->count();

        if ($approvedCount < 1) {
            $errors[] = 'Only approved questions can be published.';
        }

        $perStudent = $exam->questions_per_student;
        if ($perStudent === null || (int) $perStudent < 1) {
            $errors[] = 'Set how many questions each student answers (questions per student).';
        } elseif ((int) $perStudent > $approvedCount) {
            $errors[] = 'Questions per student cannot exceed the number of approved questions.';
        }

        $badApprovedMarksNonEssay = Question::query()
            ->where('quiz_id', $exam->id)
            ->where('pool_status', 'approved')
            ->where('type', '!=', 'essay')
            ->where(function ($q): void {
                $q->whereNull('marks')->orWhere('marks', '<=', 0);
            })
            ->exists();

        if ($badApprovedMarksNonEssay) {
            $errors[] = 'Every approved question must have marks greater than zero.';
        }

        $badEssayMarks = Question::query()
            ->where('quiz_id', $exam->id)
            ->where('pool_status', 'approved')
            ->where('type', 'essay')
            ->where(function ($q): void {
                $q->whereNull('marks')->orWhere('marks', '<=', 0);
            })
            ->exists();

        if ($badEssayMarks) {
            $errors[] = 'Essay questions require marks greater than zero.';
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
                $bad = Question::query()
                    ->where('quiz_id', $exam->id)
                    ->where('pool_status', 'approved')
                    ->whereNotIn('type', $allowedTypes)
                    ->value('type');
                $t = is_string($bad) ? $bad : 'unknown';
                $errors[] = 'Question type '.$t.' is not enabled for this assessment.';
            }

            foreach ($this->approvedQuestionShapeErrors($exam, $allowedTypes) as $msg) {
                $errors[] = $msg;
            }
        }

        if ($exam->assessment_type === 'assignment') {
            foreach (AssessmentProctoringDefaults::assignmentPublishErrors($exam) as $msg) {
                $errors[] = $msg;
            }
        }

        return array_values(array_unique($errors));
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

        $this->examRuntime->forgetExamConfig((int) $exam->id);
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

        $this->examRuntime->forgetExamConfig((int) $exam->id);
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

        $this->examRuntime->forgetExamConfig((int) $exam->id);
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
                'due_at' => $source->due_at,
                'grades_released_at' => null,
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

    /**
     * @param  list<string>  $allowedTypes
     * @return list<string>
     */
    private function approvedQuestionShapeErrors(Quiz $exam, array $allowedTypes): array
    {
        $out = [];
        $questions = Question::query()
            ->where('quiz_id', $exam->id)
            ->where('pool_status', 'approved')
            ->orderBy('id')
            ->get();

        foreach ($questions as $q) {
            if (! in_array($q->type, $allowedTypes, true)) {
                continue;
            }

            match ($q->type) {
                'mcq' => $this->appendMcqPublishShapeErrors($q, $out),
                'true_false' => $this->appendTrueFalsePublishShapeErrors($q, $out),
                'fill_blank' => $this->appendFillBlankPublishShapeErrors($q, $out),
                'essay' => $this->appendEssayPublishShapeErrors($exam, $q, $out),
                default => null,
            };
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  list<string>  $out
     */
    private function appendMcqPublishShapeErrors(Question $q, array &$out): void
    {
        $options = $q->options;
        if (! is_array($options)) {
            $out[] = 'Multiple-choice questions must have valid options and a correct answer.';

            return;
        }
        $nonEmpty = 0;
        foreach ($options as $opt) {
            if (is_string($opt) && trim($opt) !== '') {
                $nonEmpty++;
            }
        }
        if ($nonEmpty < 2) {
            $out[] = 'Multiple-choice questions must have valid options and a correct answer.';

            return;
        }

        $n = count($options);
        $correct = $q->correct_answer;
        if (! is_array($correct) || $correct === []) {
            $out[] = 'Multiple-choice questions must have valid options and a correct answer.';

            return;
        }

        foreach ($correct as $ix) {
            if (! (is_int($ix) || (is_string($ix) && preg_match('/^-?\d+$/', (string) $ix)))) {
                $out[] = 'Multiple-choice questions must have valid options and a correct answer.';

                return;
            }
            $i = (int) $ix;
            if ($i < 0 || $i >= $n) {
                $out[] = 'Multiple-choice questions must have valid options and a correct answer.';

                return;
            }
        }
    }

    /**
     * @param  list<string>  $out
     */
    private function appendTrueFalsePublishShapeErrors(Question $q, array &$out): void
    {
        $correct = $q->correct_answer;
        if (is_int($correct) && ($correct === 0 || $correct === 1)) {
            $correct = (bool) $correct;
        }
        if (! is_bool($correct)) {
            $out[] = 'True/False questions must have a valid correct answer.';
        }
    }

    /**
     * @param  list<string>  $out
     */
    private function appendFillBlankPublishShapeErrors(Question $q, array &$out): void
    {
        $groups = $this->fillBlankCorrectGroupsForPublish($q->correct_answer);
        if ($groups === null || $groups === []) {
            $out[] = 'Fill-in-the-Blank question is missing accepted answers.';

            return;
        }

        $schema = $q->answer_schema;
        $declared = is_array($schema) ? (int) ($schema['blank_count'] ?? 0) : 0;
        if ($declared !== count($groups)) {
            $out[] = 'Fill-in-the-Blank blank count does not match accepted answers.';
        }
    }

    /**
     * @param  list<string>  $out
     */
    private function appendEssayPublishShapeErrors(Quiz $exam, Question $q, array &$out): void
    {
        if ($this->examRequiresEssayMarkingGuide($exam) && ! $this->essayHasMarkingGuideOrRubric($q)) {
            $out[] = 'Essay marking guide is required by this assessment setting.';
        }
    }

    private function examRequiresEssayMarkingGuide(Quiz $exam): bool
    {
        $s = $exam->proctoring_settings;
        if (is_array($s) && array_key_exists('require_essay_marking_guide_on_publish', $s)) {
            return filter_var($s['require_essay_marking_guide_on_publish'], FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) config('exam.require_essay_marking_guide_on_publish', false);
    }

    private function essayHasMarkingGuideOrRubric(Question $q): bool
    {
        $md = $q->metadata;
        if (! is_array($md)) {
            return false;
        }

        foreach (['marking_guide', 'sample_answer'] as $key) {
            if (isset($md[$key]) && is_string($md[$key]) && trim($md[$key]) !== '') {
                return true;
            }
        }

        if (isset($md['rubric'])) {
            if (is_string($md['rubric']) && trim($md['rubric']) !== '') {
                return true;
            }
            if (is_array($md['rubric']) && $md['rubric'] !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<list<string>>|null
     */
    private function fillBlankCorrectGroupsForPublish(mixed $expected): ?array
    {
        if (! is_array($expected) || $expected === []) {
            return null;
        }

        $groups = [];
        foreach ($expected as $cell) {
            if (is_string($cell)) {
                $n = $this->normalizeBlankLifecycle($cell);
                if ($n === '') {
                    return null;
                }
                $groups[] = [$n];

                continue;
            }
            if (is_array($cell)) {
                $alts = [];
                foreach ($cell as $alt) {
                    if (! is_string($alt)) {
                        return null;
                    }
                    $t = $this->normalizeBlankLifecycle($alt);
                    if ($t !== '') {
                        $alts[] = $t;
                    }
                }
                $alts = array_values(array_unique($alts));
                if ($alts === []) {
                    return null;
                }
                $groups[] = $alts;

                continue;
            }

            return null;
        }

        return $groups;
    }

    private function normalizeBlankLifecycle(string $s): string
    {
        return preg_replace('/\s+/', ' ', trim($s)) ?? '';
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
