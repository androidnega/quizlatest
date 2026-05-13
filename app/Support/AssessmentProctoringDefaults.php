<?php

namespace App\Support;

use App\Models\Quiz;
use App\Services\ProctoringOrchestratorService;

/**
 * Default proctoring-related JSON for quizzes by assessment_type (coursework vs invigilated assessments).
 */
final class AssessmentProctoringDefaults
{
    /**
     * Baseline {@see Quiz::$proctoring_settings} after institution toggles are applied at creation time.
     *
     * @return array<string, mixed>
     */
    public static function baselineForType(
        string $assessmentType,
        bool $allowPhone,
        bool $allowFullscreen,
        bool $allowAutoSubmit,
    ): array {
        $base = ProctoringOrchestratorService::normalizeProctoringSettings([], null);

        return match ($assessmentType) {
            'assignment' => self::mergeAssignmentCourseworkDefaults($base),
            'quiz' => self::applyTier($base, allowPhone: $allowPhone, allowFullscreen: $allowFullscreen, autoSubmit: $allowAutoSubmit, light: true),
            'mid' => self::applyTier($base, allowPhone: $allowPhone, allowFullscreen: $allowFullscreen, autoSubmit: $allowAutoSubmit, light: false),
            'exam' => $base,
            default => $base,
        };
    }

    /**
     * Force coursework-safe caps on stored settings (draft saves, imports, clones).
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function enforceAssignmentCaps(array $settings): array
    {
        if (! is_array($settings)) {
            return self::mergeAssignmentCourseworkDefaults([]);
        }

        $normalized = ProctoringOrchestratorService::normalizeProctoringSettings($settings, null);
        $merged = self::mergeAssignmentCourseworkDefaults($normalized);
        $extras = array_intersect_key($settings, array_flip([
            'show_correct_answers_to_students',
            'mobile_only',
            'require_essay_marking_guide_on_publish',
            'late_acceptance_hours',
        ]));

        return array_merge($merged, $extras);
    }

    public static function isAssignment(Quiz $quiz): bool
    {
        return $quiz->assessment_type === 'assignment';
    }

    public static function assignmentClipboardBlockEnabled(?array $settings): bool
    {
        $s = is_array($settings) ? $settings : [];

        return ! array_key_exists('assignment_clipboard_block', $s)
            || filter_var($s['assignment_clipboard_block'], FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private static function mergeAssignmentCourseworkDefaults(array $base): array
    {
        $base['phone_detection_enabled'] = false;
        $base['fullscreen_enforced'] = false;
        $base['auto_submit_enabled'] = false;
        $base['violation_weights'] = array_map(static fn () => 0, $base['violation_weights'] ?? [
            'face_missing' => 0,
            'multiple_faces' => 0,
            'phone_detected' => 0,
            'fullscreen_exit' => 0,
            'essay_clipboard_attempt' => 0,
        ]);
        $base['assignment_clipboard_block'] = true;
        $base['allow_live_proctoring_for_assignment'] = false;

        return $base;
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private static function applyTier(
        array $base,
        bool $allowPhone,
        bool $allowFullscreen,
        bool $autoSubmit,
        bool $light,
    ): array {
        $base['phone_detection_enabled'] = $allowPhone ? ($light ? $base['phone_detection_enabled'] : $base['phone_detection_enabled']) : false;
        $base['fullscreen_enforced'] = $allowFullscreen ? ($light ? false : $base['fullscreen_enforced']) : false;
        $base['auto_submit_enabled'] = $autoSubmit ? ($light ? false : $base['auto_submit_enabled']) : false;

        if ($light) {
            $w = is_array($base['violation_weights'] ?? null) ? $base['violation_weights'] : [];
            foreach (['face_missing', 'multiple_faces', 'phone_detected', 'fullscreen_exit'] as $k) {
                if (isset($w[$k])) {
                    $w[$k] = (int) max(0, min((int) $w[$k], 8));
                }
            }
            $base['violation_weights'] = $w;
        }

        return $base;
    }

    /**
     * Publish-time checks for coursework (assignments) only.
     *
     * @return list<string>
     */
    public static function assignmentPublishErrors(Quiz $exam): array
    {
        $errors = [];
        if (! $exam->isAssignment()) {
            return $errors;
        }
        if (trim((string) $exam->title) === '') {
            $errors[] = 'Assignment title is required before publishing.';
        }
        if (trim((string) ($exam->description ?? '')) === '') {
            $errors[] = 'Assignment instructions are required before publishing.';
        }
        if (! $exam->targetClassrooms()->exists()) {
            $errors[] = 'Select at least one class group for this assignment.';
        }
        if ($exam->due_at === null) {
            $errors[] = 'A due date is required before publishing this assignment.';
        }

        $s = ProctoringOrchestratorService::normalizeProctoringSettings($exam->proctoring_settings, $exam->id);
        if ($s['phone_detection_enabled']) {
            $errors[] = 'Coursework assignments cannot enable phone detection for publishing.';
        }
        if ($s['fullscreen_enforced']) {
            $errors[] = 'Coursework assignments must not require fullscreen for publishing.';
        }
        if ($s['auto_submit_enabled']) {
            $errors[] = 'Coursework assignments must not enable auto-submit on violations for publishing.';
        }
        if (! self::assignmentClipboardBlockEnabled($exam->proctoring_settings)) {
            $errors[] = 'Copy and paste blocking must stay enabled for typed assignment responses before publishing.';
        }
        $live = filter_var(data_get($exam->proctoring_settings, 'allow_live_proctoring_for_assignment', false), FILTER_VALIDATE_BOOLEAN);
        if ($live) {
            $errors[] = 'Live proctoring is not allowed for coursework assignments with current policy.';
        }

        return $errors;
    }
}
