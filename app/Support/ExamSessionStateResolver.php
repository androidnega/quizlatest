<?php

namespace App\Support;

use App\Models\ExamSession;

/**
 * Backend-authoritative mapping for exam UI state (single source of truth for polling).
 */
final class ExamSessionStateResolver
{
    /**
     * @param  array<string, mixed>  $globalControl  Snapshot from ProctoringGlobalControlService::getControl()
     * @return array<string, mixed>
     */
    public static function payload(ExamSession $examSession, array $globalControl): array
    {
        $examSession->loadMissing('exam');

        $uiState = self::resolveUiState($examSession, $globalControl);

        return [
            'exam_ui_state' => $uiState,
            'session_status' => $examSession->status,
            'exam_status' => $examSession->exam_status,
            'risk_state' => $examSession->risk_state,
            'violation_score' => (int) $examSession->violation_score,
            'session_id' => $examSession->session_id,
            'exam_id' => (int) $examSession->exam_id,
            'global_control_revision' => (int) ($globalControl['revision'] ?? 0),
            'global_modules_enabled' => (bool) ($globalControl['modules_enabled'] ?? true),
            'global_emergency_shutdown' => (bool) ($globalControl['emergency_shutdown'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $globalControl
     */
    public static function resolveUiState(ExamSession $examSession, array $globalControl): string
    {
        if (! empty($globalControl['emergency_shutdown']) || empty($globalControl['modules_enabled'])) {
            return 'locked';
        }

        if ($examSession->status === 'submitted') {
            return $examSession->exam_status === 'submitted_held' ? 'held' : 'submitted';
        }

        $risk = (string) $examSession->risk_state;

        return match ($risk) {
            'locked' => 'locked',
            'warning', 'suspicious', 'critical' => 'warning',
            default => 'active',
        };
    }
}
