<?php

namespace App\Support;

use App\Models\Quiz;
use Illuminate\Validation\ValidationException;

/**
 * Allowed question types for assessments and pool enforcement.
 */
final class AssessmentQuestionTypes
{
    /** @var list<string> */
    public const ALL = ['mcq', 'true_false', 'fill_blank', 'essay'];

    /**
     * Legacy rows: null means all types are allowed.
     * An explicit empty JSON array means no types are configured (publish must block).
     *
     * @return list<string>
     */
    public static function effective(?array $stored): array
    {
        if ($stored === null) {
            return self::ALL;
        }

        $out = [];
        foreach ($stored as $t) {
            if (! is_string($t)) {
                continue;
            }
            $t = strtolower(trim($t));
            if (in_array($t, self::ALL, true)) {
                $out[$t] = true;
            }
        }

        $list = array_keys($out);
        sort($list);

        if ($list === []) {
            return $stored === [] ? [] : self::ALL;
        }

        return array_values($list);
    }

    /**
     * Intersect AI form question types with assessment-allowed types.
     *
     * @param  list<mixed>|null  $requestedFromForm
     * @param  list<string>  $allowed
     * @return list<string>
     */
    public static function intersectAiTypesWithAllowed(?array $requestedFromForm, array $allowed, string $errorKey = 'ai_question_types'): array
    {
        if ($allowed === []) {
            throw ValidationException::withMessages([
                $errorKey => [__('Configure at least one question type for this assessment before using AI generation.')],
            ]);
        }

        $req = [];
        if (is_array($requestedFromForm)) {
            foreach ($requestedFromForm as $t) {
                if (! is_string($t)) {
                    continue;
                }
                $t = strtolower(trim($t));
                if (in_array($t, self::ALL, true)) {
                    $req[$t] = true;
                }
            }
        }

        $reqList = array_keys($req);
        if ($reqList === []) {
            $reqList = ['mcq'];
        }

        $intersection = array_values(array_intersect($reqList, $allowed));
        if ($intersection === []) {
            throw ValidationException::withMessages([
                $errorKey => [__('Select at least one AI question type that is enabled for this assessment. Allowed: :allowed.', [
                    'allowed' => implode(', ', $allowed),
                ])],
            ]);
        }

        sort($intersection);

        return $intersection;
    }

    /**
     * Normalize request input for create/update (must pick at least one valid type).
     *
     * @param  array<int, mixed>|null  $raw
     * @return list<string>
     */
    public static function normalizeFromRequest(?array $raw, string $errorKey = 'selected_question_types'): array
    {
        if ($raw === null || $raw === []) {
            throw ValidationException::withMessages([
                $errorKey => [__('Select at least one question type for this assessment.')],
            ]);
        }

        $out = [];
        foreach ($raw as $t) {
            if (! is_string($t)) {
                continue;
            }
            $t = strtolower(trim($t));
            if (in_array($t, self::ALL, true)) {
                $out[$t] = true;
            }
        }

        if ($out === []) {
            throw ValidationException::withMessages([
                $errorKey => [__('Select at least one valid question type (MCQ, True/False, Fill-in-the-blank, or Essay).')],
            ]);
        }

        $list = array_keys($out);
        sort($list);

        return array_values($list);
    }

    /**
     * @param  list<array{title: string, questions: list<array<string, mixed>>}>  $sections
     */
    public static function assertSectionsOnlyUseAllowedTypes(Quiz $quiz, array $sections, string $errorKey = 'import_json'): void
    {
        $allowed = self::effective($quiz->selected_question_types);
        $violations = [];
        foreach ($sections as $si => $sec) {
            if (! is_array($sec)) {
                continue;
            }
            $questions = $sec['questions'] ?? [];
            if (! is_array($questions)) {
                continue;
            }
            foreach ($questions as $qi => $q) {
                if (! is_array($q)) {
                    continue;
                }
                $type = isset($q['type']) && is_string($q['type']) ? strtolower(trim($q['type'])) : '';
                if ($type === '' || ! in_array($type, self::ALL, true)) {
                    continue;
                }
                if (! in_array($type, $allowed, true)) {
                    $violations[] = (string) ($q['question_text'] ?? 'question').' ('.$type.')';
                }
            }
        }

        if ($violations !== []) {
            throw ValidationException::withMessages([
                $errorKey => [
                    __('This assessment only allows: :types. Remove or change questions that use other types.', [
                        'types' => implode(', ', $allowed),
                    ]),
                ],
            ]);
        }
    }

    public static function assertQuestionTypeAllowedForQuiz(Quiz $quiz, string $type, string $errorKey = 'type'): void
    {
        $type = strtolower(trim($type));
        $allowed = self::effective($quiz->selected_question_types);
        if (! in_array($type, $allowed, true)) {
            throw ValidationException::withMessages([
                $errorKey => [__('Question type :type is not enabled for this assessment. Allowed: :allowed.', [
                    'type' => $type,
                    'allowed' => implode(', ', $allowed),
                ])],
            ]);
        }
    }
}
