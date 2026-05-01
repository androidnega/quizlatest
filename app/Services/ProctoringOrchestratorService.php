<?php

namespace App\Services;

use App\Events\ExamAutoSubmitEvent;
use App\Events\ExamHeldResultEvent;
use App\Events\ProctoringRiskUpdateEvent;
use App\Events\ProctoringWarningEvent;
use App\Models\ExamSession;
use App\Models\ProctoringEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ProctoringOrchestratorService
{
    public function __construct(
        private readonly ProctoringGlobalControlService $globalControl,
    ) {}

    /** @var array<int, bool> */
    private static array $configNormalizationLoggedForExam = [];

    /** @var list<string> */
    private const REQUIRED_SETTING_KEYS = [
        'face_match_threshold',
        'tab_switch_rules',
        'phone_detection_enabled',
        'fullscreen_enforced',
        'auto_submit_enabled',
        'violation_weights',
        'cooldown_seconds',
    ];

    /** Baseline score bands (fixed; not part of DB free-form config). */
    private const INTERNAL_SCORE_BANDS = [
        'warning_score' => 30,
        'suspicious_score' => 50,
        'critical_score' => 70,
        'auto_submit_score' => 90,
    ];

    /**
     * Strict whitelist for quiz.proctoring_settings (unknown keys stripped).
     *
     * @param  array<string, mixed>|null  $raw
     * @return array<string, mixed>
     */
    public static function normalizeProctoringSettings(?array $raw, ?int $examId = null): array
    {
        $raw = is_array($raw) ? $raw : [];

        $allowed = array_flip(self::REQUIRED_SETTING_KEYS);
        $unknownKeys = array_diff(array_keys($raw), array_keys($allowed));

        $filtered = array_intersect_key($raw, $allowed);

        $defaults = [
            'face_match_threshold' => 60.0,
            'tab_switch_rules' => [1 => 10, 2 => 40, 3 => 60],
            'phone_detection_enabled' => true,
            'fullscreen_enforced' => true,
            'auto_submit_enabled' => true,
            'violation_weights' => [
                'face_missing' => 10,
                'multiple_faces' => 25,
                'phone_detected' => 20,
                'fullscreen_exit' => 10,
            ],
            'cooldown_seconds' => 45,
        ];

        $missing = [];
        foreach (self::REQUIRED_SETTING_KEYS as $key) {
            if (! array_key_exists($key, $filtered)) {
                $missing[] = $key;
            }
        }

        $normalized = [];
        foreach (self::REQUIRED_SETTING_KEYS as $key) {
            $normalized[$key] = array_key_exists($key, $filtered)
                ? self::coerceSettingValue($key, $filtered[$key], $defaults[$key])
                : $defaults[$key];
        }

        if ($examId !== null && ($unknownKeys !== [] || $missing !== []) && empty(self::$configNormalizationLoggedForExam[$examId])) {
            Log::warning('quizsnap.proctoring_settings.configuration_normalized', [
                'exam_id' => $examId,
                'unknown_keys_removed' => array_values($unknownKeys),
                'missing_keys_filled_with_defaults' => $missing,
            ]);
            self::$configNormalizationLoggedForExam[$examId] = true;
        }

        return $normalized;
    }

    private static function coerceSettingValue(string $key, mixed $value, mixed $default): mixed
    {
        return match ($key) {
            'face_match_threshold' => max(45.0, min(100.0, (float) ($value ?? $default))),
            'tab_switch_rules' => self::normalizeTabSwitchRules(is_array($value) ? $value : []),
            'phone_detection_enabled', 'fullscreen_enforced', 'auto_submit_enabled' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $default,
            'violation_weights' => self::normalizeViolationWeights(is_array($value) ? $value : []),
            'cooldown_seconds' => max(15, min(300, (int) ($value ?: $default))),
            default => $default,
        };
    }

    /**
     * @param  array<string|int, mixed>  $rules
     * @return array<int, int>
     */
    private static function normalizeTabSwitchRules(array $rules): array
    {
        $out = [];
        foreach ([1, 2, 3] as $i) {
            $out[$i] = isset($rules[$i]) ? (int) $rules[$i] : (isset($rules[(string) $i]) ? (int) $rules[(string) $i] : [1 => 10, 2 => 40, 3 => 60][$i]);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $weights
     * @return array<string, int>
     */
    private static function normalizeViolationWeights(array $weights): array
    {
        $defaults = [
            'face_missing' => 10,
            'multiple_faces' => 25,
            'phone_detected' => 20,
            'fullscreen_exit' => 10,
        ];

        $weights = array_intersect_key($weights, $defaults);

        $out = [];
        foreach ($defaults as $k => $def) {
            $out[$k] = isset($weights[$k]) ? max(0, (int) $weights[$k]) : $def;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $normalizedExamOnly  Output of normalizeProctoringSettings()
     * @return array<string, mixed>
     */
    public static function mergeInternalBandsWithNormalized(array $normalizedExamOnly): array
    {
        return array_merge(self::INTERNAL_SCORE_BANDS, $normalizedExamOnly);
    }

    /**
     * Full settings used inside ingest (canonical quiz fields + fixed bands).
     *
     * @return array<string, mixed>
     */
    private function effectiveSettings(ExamSession $examSession): array
    {
        $examSession->loadMissing('exam');
        $raw = is_array($examSession->exam?->proctoring_settings) ? $examSession->exam->proctoring_settings : [];

        $normalized = self::normalizeProctoringSettings($raw, $examSession->exam_id);
        $merged = self::mergeInternalBandsWithNormalized($normalized);

        return $this->globalControl->mergeExamSettingsForOrchestrator($merged);
    }

    public function ingestEvent(ExamSession $examSession, string $eventType, array $metadata = [], ?int $severity = null, bool $flagged = false): array
    {
        $examSession->loadMissing('exam');

        if ($this->globalControl->shouldBypassProctoringIngest()) {
            return [
                'score' => (int) $examSession->violation_score,
                'risk_state' => (string) $examSession->risk_state,
                'action' => 'log',
                'auto_submit' => false,
            ];
        }

        $settings = $this->effectiveSettings($examSession);
        $events = is_array($examSession->violation_events) ? $examSession->violation_events : [];
        $now = now();

        $previousRiskState = (string) $examSession->risk_state;

        $cooldownSeconds = (int) $settings['cooldown_seconds'];
        $inCooldown = $this->isInCooldown($events, $eventType, $now, $cooldownSeconds);

        $scoreDelta = $this->resolveScoreDelta($settings, $events, $eventType, $inCooldown);
        $newScore = ((int) $examSession->violation_score) + $scoreDelta;
        $riskState = $this->resolveRiskState($settings, $newScore);
        $action = $this->resolveAction($settings, $eventType, $events, $newScore, $inCooldown);

        $events[] = [
            'event_type' => $eventType,
            'score' => $scoreDelta,
            'timestamp' => $now->toISOString(),
            'cooldown_applied' => $inCooldown,
            'action' => $action,
        ];

        ProctoringEvent::create([
            'user_id' => $examSession->student_id,
            'quiz_id' => $examSession->exam_id,
            'event_type' => $eventType,
            'severity' => $severity ?? 1,
            'flagged' => $flagged,
            'action_taken' => $action,
            'metadata' => [
                'session_id' => $examSession->session_id,
                'student_id' => $examSession->student_id,
                'exam_id' => $examSession->exam_id,
                'risk_state' => $riskState,
                'payload' => $metadata,
            ],
            'created_at' => $now,
        ]);

        $examSession->update([
            'violation_count' => (int) $examSession->violation_count + ($flagged ? 1 : 0),
            'violation_score' => $newScore,
            'violation_events' => $events,
            'last_event_time' => $now,
            'risk_state' => $riskState,
            'exam_status' => $newScore >= (int) data_get($settings, 'suspicious_score', 50)
                ? 'flagged_for_review'
                : $examSession->exam_status,
        ]);

        $decision = [
            'score' => $newScore,
            'risk_state' => $riskState,
            'action' => $action,
            'auto_submit' => $action === 'autosubmit',
        ];

        $this->dispatchRealtimeNotifications(
            examSession: $examSession,
            eventType: $eventType,
            previousRiskState: $previousRiskState,
            riskState: $riskState,
            violationScore: $newScore,
            action: $action,
        );

        return $decision;
    }

    private function dispatchRealtimeNotifications(
        ExamSession $examSession,
        string $eventType,
        string $previousRiskState,
        string $riskState,
        int $violationScore,
        string $action,
    ): void {
        if ($riskState !== $previousRiskState) {
            broadcast(new ProctoringRiskUpdateEvent(
                sessionId: $examSession->session_id,
                examId: (int) $examSession->exam_id,
                studentId: (int) $examSession->student_id,
                riskState: $riskState,
                violationScore: $violationScore,
                previousRiskState: $previousRiskState,
            ));
        }

        if ($action === 'warn') {
            broadcast(new ProctoringWarningEvent(
                sessionId: $examSession->session_id,
                examId: (int) $examSession->exam_id,
                studentId: (int) $examSession->student_id,
                message: $this->warningMessage($eventType),
                riskState: $riskState,
                violationScore: $violationScore,
                eventType: $eventType,
            ));
        }

        if ($action === 'autosubmit') {
            broadcast(new ExamAutoSubmitEvent(
                sessionId: $examSession->session_id,
                examId: (int) $examSession->exam_id,
                studentId: (int) $examSession->student_id,
                reason: $eventType === 'tab_switch' ? 'tab_switch_escalation' : 'violation_threshold',
                violationScore: $violationScore,
                riskState: $riskState,
            ));

            broadcast(new ExamHeldResultEvent(
                sessionId: $examSession->session_id,
                examId: (int) $examSession->exam_id,
                studentId: (int) $examSession->student_id,
                message: 'Your exam has been submitted due to violation detection. Your result is under review. Please contact your lecturer.',
                reason: 'submitted_held',
            ));
        }
    }

    private function warningMessage(string $eventType): string
    {
        return match ($eventType) {
            'tab_switch' => 'You moved away from the exam tab or window. Please return and stay focused.',
            'fullscreen_exit' => 'Fullscreen mode ended. Please restore fullscreen if required by your institution.',
            'face_missing' => 'Your face was not visible to the camera.',
            'multiple_faces' => 'Multiple faces were detected near your workstation.',
            'phone_detected' => 'A phone-like object was detected in frame.',
            default => 'A proctoring concern was detected. Please adjust your setup.',
        };
    }

    private function resolveScoreDelta(array $settings, array $events, string $eventType, bool $inCooldown): int
    {
        if ($inCooldown) {
            return 0;
        }

        if ($eventType === 'fullscreen_exit' && empty($settings['fullscreen_enforced'])) {
            return 0;
        }

        if ($eventType === 'phone_detected' && empty($settings['phone_detection_enabled'])) {
            return 0;
        }

        if ($eventType === 'tab_switch') {
            $occurrence = collect($events)->where('event_type', 'tab_switch')->count() + 1;
            $tabRule = is_array($settings['tab_switch_rules'] ?? null)
                ? $settings['tab_switch_rules']
                : [1 => 10, 2 => 40, 3 => 60];

            return (int) ($tabRule[$occurrence] ?? 0);
        }

        /** @var array<string, int> $weights */
        $weights = is_array($settings['violation_weights'] ?? null)
            ? $settings['violation_weights']
            : self::normalizeViolationWeights([]);

        return (int) ($weights[$eventType] ?? 0);
    }

    private function resolveAction(array $settings, string $eventType, array $events, int $newScore, bool $inCooldown): string
    {
        if ($eventType === 'tab_switch' && ! $inCooldown) {
            $occurrence = collect($events)->where('event_type', 'tab_switch')->count() + 1;
            if ($occurrence === 1) {
                return 'log';
            }
            if ($occurrence === 2) {
                return 'warn';
            }
            if ($occurrence >= 3) {
                return 'autosubmit';
            }
        }

        $criticalScore = (int) data_get($settings, 'auto_submit_score', 90);
        $autoSubmitEnabled = filter_var($settings['auto_submit_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);

        if ($autoSubmitEnabled && $newScore >= $criticalScore) {
            return 'autosubmit';
        }

        if ($newScore >= (int) data_get($settings, 'warning_score', 30)) {
            return 'warn';
        }

        return 'log';
    }

    private function resolveRiskState(array $settings, int $score): string
    {
        $warning = (int) data_get($settings, 'warning_score', 30);
        $suspicious = (int) data_get($settings, 'suspicious_score', 50);
        $critical = (int) data_get($settings, 'critical_score', 70);
        $locked = (int) data_get($settings, 'auto_submit_score', 90);

        return match (true) {
            $score >= $locked => 'locked',
            $score >= $critical => 'critical',
            $score >= $suspicious => 'suspicious',
            $score >= $warning => 'warning',
            default => 'normal',
        };
    }

    private function isInCooldown(array $events, string $eventType, Carbon $now, int $cooldownSeconds): bool
    {
        $lastSame = collect($events)->reverse()->first(fn ($event) => ($event['event_type'] ?? '') === $eventType);
        if (! is_array($lastSame) || empty($lastSame['timestamp'])) {
            return false;
        }

        return $now->diffInSeconds($lastSame['timestamp']) < $cooldownSeconds;
    }
}
