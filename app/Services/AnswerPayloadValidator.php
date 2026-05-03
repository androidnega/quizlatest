<?php

namespace App\Services;

use App\Models\Question;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Strict client answer contract (answer_payload only; answer_text ignored for grading).
 */
final class AnswerPayloadValidator
{
    /**
     * @param  array<string, mixed>|null  $payload
     *
     * @throws ValidationException
     */
    public static function validate(Question $question, ?array $payload): array
    {
        if ($payload === null) {
            throw ValidationException::withMessages([
                'answer_payload' => ['The answer_payload field is required.'],
            ]);
        }

        $base = Validator::make($payload, [
            'type' => ['required', 'string', 'in:mcq,true_false,fill_blank,essay'],
        ]);

        if ($base->fails()) {
            throw new ValidationException($base);
        }

        if ($payload['type'] !== $question->type) {
            throw ValidationException::withMessages([
                'answer_payload.type' => ['Payload type must match the question type.'],
            ]);
        }

        return match ($question->type) {
            'mcq' => self::validateMcq($payload),
            'true_false' => self::validateTrueFalse($payload),
            'fill_blank' => self::validateFillBlank($payload),
            'essay' => self::validateEssay($payload),
            default => throw ValidationException::withMessages([
                'answer_payload' => ['Unsupported question type.'],
            ]),
        };
    }

    /**
     * Validate MCQ indices against displayed option count (after shuffle).
     *
     * @param  array<string, mixed>  $payload
     * @return array{type: string, selected: list<int>}
     */
    public static function validateMcqDisplayIndices(array $payload, int $displayOptionCount): array
    {
        if ($displayOptionCount < 1) {
            throw ValidationException::withMessages([
                'answer_payload.selected' => ['Invalid MCQ display option count.'],
            ]);
        }

        $parsed = self::parseMcqSelectedIndices($payload);
        foreach ($parsed as $i) {
            if ($i < 0 || $i >= $displayOptionCount) {
                throw ValidationException::withMessages([
                    'answer_payload.selected' => ['Selected option is out of range for this question.'],
                ]);
            }
        }

        return [
            'type' => 'mcq',
            'selected' => $parsed,
        ];
    }

    /**
     * Map display indices to original option indices using per-session shuffle map.
     *
     * @param  list<int>  $displayToOriginal
     * @return array{type: string, selected: list<int>}
     */
    public static function remapMcqPayloadToOriginalIndices(array $payload, array $displayToOriginal): array
    {
        $displayCount = count($displayToOriginal);
        $validated = self::validateMcqDisplayIndices($payload, $displayCount);

        $original = [];
        foreach ($validated['selected'] as $d) {
            $original[] = $displayToOriginal[$d];
        }
        sort($original);

        return [
            'type' => 'mcq',
            'selected' => array_values(array_unique($original)),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<int>
     */
    private static function parseMcqSelectedIndices(array $payload): array
    {
        $raw = $payload['selected'] ?? null;
        $indices = [];

        if (is_int($raw) || (is_string($raw) && preg_match('/^-?\d+$/', $raw))) {
            $indices = [(int) $raw];
        } elseif (is_array($raw)) {
            foreach ($raw as $item) {
                if (is_int($item) || (is_string($item) && ctype_digit((string) $item))) {
                    $indices[] = (int) $item;
                } else {
                    throw ValidationException::withMessages([
                        'answer_payload.selected' => ['MCQ selected indices must be integers.'],
                    ]);
                }
            }
        } else {
            throw ValidationException::withMessages([
                'answer_payload.selected' => ['MCQ selected must be an index or array of indices.'],
            ]);
        }

        $indices = array_values(array_unique($indices));
        if ($indices === []) {
            throw ValidationException::withMessages([
                'answer_payload.selected' => ['Select at least one option.'],
            ]);
        }

        return $indices;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{type: string, selected: list<int>}
     */
    private static function validateMcq(array $payload): array
    {
        $v = Validator::make($payload, [
            'selected' => ['required'],
        ]);

        if ($v->fails()) {
            throw new ValidationException($v);
        }

        $indices = self::parseMcqSelectedIndices($payload);

        foreach ($indices as $i) {
            if ($i < 0) {
                throw ValidationException::withMessages([
                    'answer_payload.selected' => ['Indices must be non-negative.'],
                ]);
            }
        }

        return [
            'type' => 'mcq',
            'selected' => $indices,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{type: string, value: bool}
     */
    private static function validateTrueFalse(array $payload): array
    {
        $v = Validator::make($payload, [
            'value' => ['required', 'boolean'],
        ]);

        if ($v->fails()) {
            throw new ValidationException($v);
        }

        return [
            'type' => 'true_false',
            'value' => (bool) $payload['value'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{type: string, blanks: list<string>}
     */
    private static function validateFillBlank(array $payload): array
    {
        $v = Validator::make($payload, [
            'blanks' => ['required', 'array', 'min:1'],
            'blanks.*' => ['required', 'string', 'max:5000'],
        ]);

        if ($v->fails()) {
            throw new ValidationException($v);
        }

        /** @var list<string> $blanks */
        $blanks = array_values(array_map(fn ($s) => (string) $s, Arr::wrap($payload['blanks'])));

        return [
            'type' => 'fill_blank',
            'blanks' => $blanks,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{type: string, text: string}
     */
    private static function validateEssay(array $payload): array
    {
        if (! array_key_exists('text', $payload)) {
            throw ValidationException::withMessages([
                'answer_payload.text' => ['The essay text field is required.'],
            ]);
        }

        $v = Validator::make($payload, [
            'text' => ['nullable', 'string', 'max:50000'],
        ]);

        if ($v->fails()) {
            throw new ValidationException($v);
        }

        return [
            'type' => 'essay',
            'text' => (string) $payload['text'],
        ];
    }
}
