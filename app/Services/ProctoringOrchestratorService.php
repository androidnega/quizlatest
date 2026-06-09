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
        private readonly SystemExamPolicyService $examPolicy,
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

    /**
     * @var list<string>
     */
    private const AUXILIARY_SETTING_KEYS = [
        'violation_actions',
        'violation_deduct_marks_per_flag',
        'show_correct_answers_to_students',
        'mobile_only',
        'require_essay_marking_guide_on_publish',
        'assignment_clipboard_block',
        'allow_live_proctoring_for_assignment',
        'phone_detection_confidence_threshold',
        'screenshot_autosubmit_enabled',
        'external_display_detection_enabled',
        'face_covered_flag_after_strikes',
        'tab_switch_debounce_seconds',
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
        $known = [...self::REQUIRED_SETTING_KEYS, ...self::AUXILIARY_SETTING_KEYS];
        $unknownKeys = array_values(array_diff(array_keys($raw), $known));

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
                'essay_clipboard_attempt' => 0,
                'exam_integrity_signal' => 0,
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
                'unknown_keys_removed' => $unknownKeys,
                'missing_keys_filled_with_defaults' => $missing,
            ]);
            self::$configNormalizationLoggedForExam[$examId] = true;
        }

        $normalized['violation_actions'] = self::normalizeViolationActions($raw['violation_actions'] ?? null);

        if (array_key_exists('violation_deduct_marks_per_flag', $raw)) {
            $normalized['violation_deduct_marks_per_flag'] = max(0.0, (float) $raw['violation_deduct_marks_per_flag']);
        }

        if (array_key_exists('show_correct_answers_to_students', $raw)) {
            $normalized['show_correct_answers_to_students'] = filter_var(
                $raw['show_correct_answers_to_students'],
                FILTER_VALIDATE_BOOLEAN,
            );
        }

        if (array_key_exists('mobile_only', $raw)) {
            $normalized['mobile_only'] = filter_var($raw['mobile_only'], FILTER_VALIDATE_BOOLEAN);
        }

        if (array_key_exists('assignment_clipboard_block', $raw)) {
            $normalized['assignment_clipboard_block'] = filter_var(
                $raw['assignment_clipboard_block'],
                FILTER_VALIDATE_BOOLEAN,
            );
        }

        if (array_key_exists('allow_live_proctoring_for_assignment', $raw)) {
            $normalized['allow_live_proctoring_for_assignment'] = filter_var(
                $raw['allow_live_proctoring_for_assignment'],
                FILTER_VALIDATE_BOOLEAN,
            );
        }

        $normalized['phone_detection_confidence_threshold'] = isset($raw['phone_detection_confidence_threshold'])
            ? max(0.35, min(0.99, (float) $raw['phone_detection_confidence_threshold']))
            : 0.55;

        $normalized['screenshot_autosubmit_enabled'] = filter_var(
            $raw['screenshot_autosubmit_enabled'] ?? false,
            FILTER_VALIDATE_BOOLEAN,
        );

        $normalized['external_display_detection_enabled'] = filter_var(
            $raw['external_display_detection_enabled'] ?? true,
            FILTER_VALIDATE_BOOLEAN,
        );

        $normalized['face_covered_flag_after_strikes'] = isset($raw['face_covered_flag_after_strikes'])
            ? max(3, min(30, (int) $raw['face_covered_flag_after_strikes']))
            : 6;

        $normalized['tab_switch_debounce_seconds'] = isset($raw['tab_switch_debounce_seconds'])
            ? max(2, min(15, (int) $raw['tab_switch_debounce_seconds']))
            : 3;

        return $normalized;
    }

    /**
     * Policy flags stored on the quiz. Note: {@see ResultFinalizationService} does not apply
     * automatic mark deductions from violations; examiners review (e.g. held results) instead.
     *
     * @return array{warn: bool, deduct: bool, autosubmit: bool}
     */
    private static function normalizeViolationActions(mixed $raw): array
    {
        $src = is_array($raw) ? $raw : [];

        return [
            'warn' => filter_var($src['warn'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'deduct' => filter_var($src['deduct'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'autosubmit' => filter_var($src['autosubmit'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
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
            'essay_clipboard_attempt' => 0,
            'exam_integrity_signal' => 0,
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

    /**
     * @return array{
     *   score: int,
     *   risk_state: string,
     *   action: string,
     *   auto_submit: bool,
     *   client_message: ?string,
     *   tab_switch_count: int,
     *   proctoring_overlay: array{active: bool, reason: ?string, message: ?string}
     * }
     */
    public function ingestEvent(ExamSession $examSession, string $eventType, array $metadata = [], ?int $severity = null, bool $flagged = false): array
    {
        $examSession->loadMissing('exam');

        if ($this->globalControl->shouldBypassProctoringIngest()) {
            return $this->emptyDecision($examSession);
        }

        $settings = $this->effectiveSettings($examSession);
        $now = now();

        return match ($eventType) {
            'tab_switch' => $this->ingestTabSwitch($examSession, $settings, $metadata, $severity, $flagged, $now),
            'phone_detected' => $this->ingestPhoneDetected($examSession, $settings, $metadata, $severity, $flagged, $now),
            'multiple_faces' => $this->ingestMultipleFaces($examSession, $settings, $metadata, $severity, $flagged, $now),
            'face_covered', 'face_obstructed', 'face_not_clear' => $this->ingestFaceObstruction(
                $examSession,
                $settings,
                $metadata,
                $severity,
                $flagged,
                $now,
                $eventType,
            ),
            'possible_screenshot_attempt' => $this->ingestScreenshotAttempt($examSession, $settings, $metadata, $severity, $flagged, $now),
            'possible_screen_record_attempt' => $this->ingestScreenRecordAttempt($examSession, $metadata, $severity, $now),
            'external_display_risk' => $this->ingestExternalDisplayRisk($examSession, $settings, $metadata, $severity, $flagged, $now),
            'proctoring_overlay_resolved' => $this->ingestOverlayResolved($examSession, $metadata, $now),
            default => $this->ingestLegacyWeightedEvent($examSession, $eventType, $settings, $metadata, $severity, $flagged, $now),
        };
    }

    /** Multiple faces must be visible continuously for this many seconds before we auto-submit. */
    public const MULTIPLE_FACES_AUTO_SUBMIT_SECONDS = 30;

    /**
     * Multiple faces: auto-submit when 2+ faces are continuously detected
     * for {@see self::MULTIPLE_FACES_AUTO_SUBMIT_SECONDS} seconds. Until
     * then we surface progressive warnings.
     *
     * The client sends two pieces of metadata on each tick:
     *  - "duration_ms" — how long the second face has been continuously visible
     *  - "phase"       — "started" | "continuing" | "auto_submit_threshold_reached"
     *
     * The server treats duration_ms as authoritative (it can also reconstruct
     * the duration from prior events as a fallback for older clients).
     *
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function ingestMultipleFaces(
        ExamSession $examSession,
        array $settings,
        array $metadata,
        ?int $severity,
        bool $flagged,
        Carbon $now,
    ): array {
        // Audit P1.6 / Phase 6: route binding produced a fresh row and the
        // batch controller now passes the same model across events. Reading
        // the same row again here would issue a useless SELECT per event.
        $session = $examSession;
        $events = is_array($session->violation_events) ? $session->violation_events : [];

        // Debounce identical strikes; we only need a tick every ~10 s for UI.
        $cooldownSeconds = 8;
        if ($this->isInCooldown($session, 'multiple_faces', $now, $cooldownSeconds)) {
            return array_merge($this->emptyDecision($session), ['action' => 'debounced']);
        }

        $clientDurationMs = isset($metadata['duration_ms']) ? (int) $metadata['duration_ms'] : 0;
        $serverDurationSeconds = $this->durationFromContinuousEvents($session, 'multiple_faces', $now);
        $durationSeconds = max((int) round($clientDurationMs / 1000), $serverDurationSeconds);

        $thresholdSeconds = self::MULTIPLE_FACES_AUTO_SUBMIT_SECONDS;
        $previousRiskState = (string) $session->risk_state;
        $autoSubmit = $durationSeconds >= $thresholdSeconds;
        $action = $autoSubmit ? 'autosubmit' : 'warn';

        $remaining = max(0, $thresholdSeconds - $durationSeconds);
        $clientMessage = $autoSubmit
            ? "Multiple faces were visible for {$thresholdSeconds} seconds. Your assessment has been submitted for review."
            : ($remaining > 0
                ? "Multiple faces detected. Make sure only you are visible — auto-submit in {$remaining}s if it continues."
                : 'Multiple faces detected. Make sure only you are visible to the camera.');

        // Weighted score still moves the risk bar, but the decisive rule is the time-based one above.
        $weights = is_array($settings['violation_weights'] ?? null)
            ? $settings['violation_weights']
            : self::normalizeViolationWeights([]);
        $scoreDelta = (int) ($weights['multiple_faces'] ?? 25);
        $newScore = ((int) $session->violation_score) + $scoreDelta;
        $riskState = $autoSubmit
            ? 'locked'
            : $this->resolveRiskState($settings, $newScore);

        $events[] = [
            'event_type' => 'multiple_faces',
            'score' => $scoreDelta,
            'timestamp' => $now->toISOString(),
            'cooldown_applied' => false,
            'action' => $action,
            'duration_seconds' => $durationSeconds,
            'threshold_seconds' => $thresholdSeconds,
        ];

        $this->persistProctoringEvent(
            $session,
            'multiple_faces',
            $metadata + ['duration_seconds' => $durationSeconds, 'threshold_seconds' => $thresholdSeconds],
            $severity ?? 2,
            true,
            $action,
            $riskState,
            $now,
        );

        $examStatus = (string) ($session->exam_status ?? 'active');
        if (! $autoSubmit && $session->status !== 'submitted') {
            $examStatus = 'flagged_for_review';
        }

        // Audit P1.6: ->update() refreshes attributes in-place; no need
        // for another SELECT here.
        $session->update([
            'violation_score' => $newScore,
            'violation_count' => (int) $session->violation_count + 1,
            'last_violation_at' => $now,
            'risk_state' => $riskState,
            'exam_status' => $examStatus,
        ]);

        $this->dispatchRealtimeNotifications(
            $session,
            'multiple_faces',
            $previousRiskState,
            $riskState,
            $newScore,
            $action,
            $autoSubmit ? null : $clientMessage,
            $autoSubmit ? $clientMessage : null,
            $newScore - $scoreDelta,
        );

        return [
            'score' => $newScore,
            'risk_state' => $riskState,
            'action' => $action,
            'auto_submit' => $autoSubmit,
            'client_message' => $clientMessage,
            'tab_switch_count' => (int) ($session->tab_switch_count ?? 0),
            'proctoring_overlay' => [
                'active' => (bool) ($session->proctoring_blur_active ?? false),
                'reason' => $session->proctoring_blur_reason,
                'message' => null,
            ],
        ];
    }

    /**
     * Architecture Review Phase 3: cooldown / duration / continuous-streak
     * computations are served from the relational `proctoring_events`
     * table instead of the formerly-unbounded `exam_sessions.violation_events`
     * JSON column. The composite index
     * (user_id, quiz_id, event_type, created_at) makes this a single
     * indexed seek per call.
     *
     * Computes the duration of the most recent continuous streak of the
     * named event_type for this exam attempt. A "gap" longer than 15 s
     * breaks the streak. Used as a server-side fallback when the client
     * does not (or cannot) report duration_ms itself.
     */
    private function durationFromContinuousEvents(ExamSession $session, string $eventType, Carbon $now): int
    {
        $maxGapSeconds = 15;
        $studentId = (int) $session->student_id;
        $examId = (int) $session->exam_id;

        // Pull the most recent N events of this type for this attempt
        // and walk them in PHP. N=200 is far more than any plausible
        // continuous-streak depth (the longest current threshold is the
        // 30-second multiple_faces auto-submit, which at the maximum
        // 1-event-per-second client cadence could reach ~30 entries).
        $rows = ProctoringEvent::query()
            ->where('user_id', $studentId)
            ->where('quiz_id', $examId)
            ->where('event_type', $eventType)
            ->orderByDesc('id')
            ->limit(200)
            ->pluck('created_at');

        $oldestStreakStart = null;
        $lastSeen = null;
        foreach ($rows as $createdAt) {
            if ($createdAt === null) {
                break;
            }
            $time = $createdAt instanceof Carbon ? $createdAt : Carbon::parse((string) $createdAt);
            if ($lastSeen !== null && abs($lastSeen->diffInSeconds($time)) > $maxGapSeconds) {
                break;
            }
            $lastSeen = $time;
            $oldestStreakStart = $time;
        }

        if ($oldestStreakStart === null) {
            return 0;
        }

        return (int) max(0, abs($oldestStreakStart->diffInSeconds($now)));
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDecision(ExamSession $examSession): array
    {
        return [
            'score' => (int) $examSession->violation_score,
            'risk_state' => (string) $examSession->risk_state,
            'action' => 'log',
            'auto_submit' => false,
            'client_message' => null,
            'tab_switch_count' => (int) ($examSession->tab_switch_count ?? 0),
            'proctoring_overlay' => [
                'active' => (bool) ($examSession->proctoring_blur_active ?? false),
                'reason' => $examSession->proctoring_blur_reason,
                'message' => null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function ingestTabSwitch(
        ExamSession $examSession,
        array $settings,
        array $metadata,
        ?int $severity,
        bool $flagged,
        Carbon $now,
    ): array {
        // Audit P1.6 / Phase 6: skip the unnecessary fresh() reads.
        $session = $examSession;
        $events = is_array($session->violation_events) ? $session->violation_events : [];
        $debounceSec = (int) data_get($settings, 'tab_switch_debounce_seconds', 3);
        $debounceSec = max(2, min(15, $debounceSec));
        if ($this->isInCooldown($session, 'tab_switch', $now, $debounceSec)) {
            return array_merge($this->emptyDecision($session), [
                'action' => 'debounced',
                'client_message' => null,
            ]);
        }

        $previousRiskState = (string) $session->risk_state;
        // increment() updates the attribute in-place; no need to re-fetch.
        $session->increment('tab_switch_count');
        $strike = (int) ($session->tab_switch_count ?? 0);

        $action = $strike >= 3 ? 'autosubmit' : 'warn';
        $clientMessage = match (true) {
            $strike === 1 => 'Please stay on the assessment page. Leaving the page may submit your work.',
            $strike === 2 => 'Warning: You have left the assessment screen again. A third time will submit your work.',
            default => 'Your assessment has been submitted because you left the screen three times.',
        };

        $riskState = match (true) {
            $strike >= 3 => 'locked',
            $strike === 2 => 'suspicious',
            default => 'warning',
        };

        $examStatus = (string) ($session->exam_status ?? 'active');
        if ($strike === 2 && $session->status !== 'submitted') {
            $examStatus = 'flagged_for_review';
        }

        $events[] = [
            'event_type' => 'tab_switch',
            'score' => 0,
            'timestamp' => $now->toISOString(),
            'cooldown_applied' => false,
            'action' => $action,
            'tab_switch_strike' => $strike,
        ];

        $this->persistProctoringEvent(
            $session,
            'tab_switch',
            $metadata,
            $severity ?? 1,
            $strike >= 2,
            $action,
            $riskState,
            $now,
        );

        $session->update([
            'last_violation_at' => $now,
            'risk_state' => $riskState,
            'exam_status' => $examStatus,
            'violation_count' => (int) $session->violation_count + ($strike === 2 ? 1 : 0),
        ]);

        $warnMessage = $strike < 3 ? $clientMessage : null;
        $this->dispatchRealtimeNotifications(
            $session,
            'tab_switch',
            $previousRiskState,
            $riskState,
            (int) ($session->violation_score ?? 0),
            $action,
            $warnMessage,
            $strike >= 3 ? $clientMessage : null,
        );

        return [
            'score' => (int) ($session->violation_score ?? 0),
            'risk_state' => $riskState,
            'action' => $action,
            'auto_submit' => $action === 'autosubmit',
            'client_message' => $clientMessage,
            'tab_switch_count' => $strike,
            'proctoring_overlay' => [
                'active' => (bool) ($session->proctoring_blur_active ?? false),
                'reason' => $session->proctoring_blur_reason ?? null,
                'message' => null,
            ],
        ];
    }

    /**
     * Camera-based phone detection: auto-submit when confidence meets threshold (no mark deduction).
     *
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function ingestPhoneDetected(
        ExamSession $examSession,
        array $settings,
        array $metadata,
        ?int $severity,
        bool $flagged,
        Carbon $now,
    ): array {
        if (empty($settings['phone_detection_enabled'])) {
            return $this->emptyDecision($examSession);
        }

        $threshold = (float) data_get($settings, 'phone_detection_confidence_threshold', 0.55);
        $confidence = isset($metadata['confidence']) ? (float) $metadata['confidence'] : 0.0;
        if ($confidence < $threshold) {
            return $this->emptyDecision($examSession);
        }

        // Audit P1.6 / Phase 6: route binding produced a fresh row.
        $session = $examSession;
        $events = is_array($session->violation_events) ? $session->violation_events : [];
        $cooldownSeconds = max(20, (int) ($settings['cooldown_seconds'] ?? 45));
        if ($this->isInCooldown($session, 'phone_detected', $now, $cooldownSeconds)) {
            return array_merge($this->emptyDecision($session), ['action' => 'debounced']);
        }

        $metadata['detection_method'] = 'camera_based_object_detection';
        $metadata['confidence_threshold'] = $threshold;
        $metadata['label'] = 'Camera-based phone detection is probabilistic and not guaranteed.';

        $action = 'autosubmit';
        $riskState = 'locked';
        $prevRisk = (string) $session->risk_state;

        $events[] = [
            'event_type' => 'phone_detected',
            'score' => 0,
            'timestamp' => $now->toISOString(),
            'cooldown_applied' => false,
            'action' => $action,
        ];

        $this->persistProctoringEvent(
            $session,
            'phone_detected',
            $metadata,
            $severity ?? 3,
            true,
            $action,
            $riskState,
            $now,
        );

        $session->update([
            'last_violation_at' => $now,
            'risk_state' => $riskState,
            'exam_status' => 'flagged_for_review',
            'violation_count' => (int) $session->violation_count + 1,
        ]);

        $msg = 'A mobile device was detected. Your assessment has been submitted for review.';
        $this->dispatchRealtimeNotifications(
            $session,
            'phone_detected',
            $prevRisk,
            $riskState,
            (int) ($session->violation_score ?? 0),
            $action,
            null,
            $msg,
        );

        return [
            'score' => (int) ($session->violation_score ?? 0),
            'risk_state' => $riskState,
            'action' => $action,
            'auto_submit' => true,
            'client_message' => $msg,
            'tab_switch_count' => (int) ($session->tab_switch_count ?? 0),
            'proctoring_overlay' => ['active' => false, 'reason' => null, 'message' => null],
        ];
    }

    /**
     * Face obstruction / unclear face (distinct from face_missing when no face is seen).
     *
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function ingestFaceObstruction(
        ExamSession $examSession,
        array $settings,
        array $metadata,
        ?int $severity,
        bool $flagged,
        Carbon $now,
        string $logEventType,
    ): array {
        // Audit P1.6 / Phase 6.
        $session = $examSession;
        $events = is_array($session->violation_events) ? $session->violation_events : [];
        $cooldownSeconds = max(10, min(120, (int) ($settings['cooldown_seconds'] ?? 45)));
        $faceFamily = ['face_covered', 'face_obstructed', 'face_not_clear'];
        if ($this->isInCooldownAny($session, $faceFamily, $now, min(20, $cooldownSeconds))) {
            return array_merge($this->emptyDecision($session), ['action' => 'debounced']);
        }

        $prevRisk = (string) $session->risk_state;
        // increment() updates the attribute in-place.
        $session->increment('face_covered_strike_count');
        $strikes = (int) ($session->face_covered_strike_count ?? 0);
        $flagAfter = (int) data_get($settings, 'face_covered_flag_after_strikes', 6);

        $action = 'warn';
        $clientMessage = 'Please remove your hand or anything covering your face.';
        $riskState = 'warning';
        $examStatus = (string) ($session->exam_status ?? 'active');
        $blurActive = false;

        if ($strikes >= $flagAfter) {
            $riskState = 'suspicious';
            $examStatus = 'flagged_for_review';
            $blurActive = true;
            $action = 'blur';
            $clientMessage = 'Your face must stay clearly visible. The screen is locked until you fix your camera setup; press continue when ready.';
        }

        $metadata['obstruction_signal'] = $logEventType;

        $events[] = [
            'event_type' => $logEventType,
            'score' => 0,
            'timestamp' => $now->toISOString(),
            'cooldown_applied' => false,
            'action' => $action,
            'strike' => $strikes,
        ];

        $this->persistProctoringEvent(
            $session,
            $logEventType,
            $metadata,
            $severity ?? 1,
            $strikes >= $flagAfter,
            $action,
            $riskState,
            $now,
        );

        $session->update([
            'last_violation_at' => $now,
            'risk_state' => $riskState,
            'exam_status' => $examStatus,
            'proctoring_blur_active' => $blurActive ? true : (bool) ($session->proctoring_blur_active ?? false),
            'proctoring_blur_reason' => $blurActive ? 'face_obstruction' : $session->proctoring_blur_reason,
        ]);

        $this->dispatchRealtimeNotifications($session, $logEventType, $prevRisk, $riskState, (int) ($session->violation_score ?? 0), $action, $clientMessage, null);

        $overlayMessage = null;
        if (! empty($session->proctoring_blur_active)) {
            $overlayMessage = match ((string) ($session->proctoring_blur_reason ?? '')) {
                'face_obstruction' => 'Your face must stay clearly visible. Adjust your position, then tap continue when your face is clearly on camera.',
                'external_display' => 'External display risk detected. Disconnect it to continue.',
                default => 'Please resolve the issue to continue.',
            };
        }

        return [
            'score' => (int) ($session->violation_score ?? 0),
            'risk_state' => $riskState,
            'action' => $action,
            'auto_submit' => false,
            'client_message' => $clientMessage,
            'tab_switch_count' => (int) ($session->tab_switch_count ?? 0),
            'proctoring_overlay' => [
                'active' => (bool) ($session->proctoring_blur_active ?? false),
                'reason' => $session->proctoring_blur_reason,
                'message' => $overlayMessage,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function ingestScreenshotAttempt(
        ExamSession $examSession,
        array $settings,
        array $metadata,
        ?int $severity,
        bool $flagged,
        Carbon $now,
    ): array {
        $metadata['detection_note'] = 'Browser-based logging only; screenshots cannot be fully detected or prevented.';

        // Audit P1.6 / Phase 6: route binding produced a fresh row.
        $session = $examSession;
        $events = is_array($session->violation_events) ? $session->violation_events : [];

        // Screenshot attempts always auto-submit. The legacy
        // `screenshot_autosubmit_enabled` flag is retained for downstream
        // tooling that reads it, but is no longer required for enforcement.
        unset($settings);
        $auto = true;
        $action = 'autosubmit';
        $riskState = 'locked';
        $prevRisk = (string) $session->risk_state;

        $events[] = [
            'event_type' => 'possible_screenshot_attempt',
            'score' => 0,
            'timestamp' => $now->toISOString(),
            'cooldown_applied' => false,
            'action' => $action,
        ];

        $this->persistProctoringEvent(
            $session,
            'possible_screenshot_attempt',
            $metadata,
            $severity ?? 1,
            false,
            $action,
            $riskState,
            $now,
        );

        $session->update([
            'last_violation_at' => $now,
            'risk_state' => $riskState,
            'exam_status' => 'flagged_for_review',
        ]);

        $msg = 'Screenshot attempts are not allowed. Your assessment has been submitted for review.';
        $this->dispatchRealtimeNotifications(
            $session,
            'possible_screenshot_attempt',
            $prevRisk,
            $riskState,
            (int) ($session->violation_score ?? 0),
            $action,
            $auto ? null : $msg,
            $auto ? $msg : null,
        );

        return [
            'score' => (int) ($session->violation_score ?? 0),
            'risk_state' => $riskState,
            'action' => $action,
            'auto_submit' => $auto,
            'client_message' => $msg,
            'tab_switch_count' => (int) ($session->tab_switch_count ?? 0),
            'proctoring_overlay' => ['active' => false, 'reason' => null, 'message' => null],
        ];
    }

    /**
     * Screen recording attempts (keyboard shortcuts like Cmd+Shift+5,
     * Win+G, or the `getDisplayMedia` Web API being invoked) are treated
     * as an immediate, unconditional auto-submit.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function ingestScreenRecordAttempt(
        ExamSession $examSession,
        array $metadata,
        ?int $severity,
        Carbon $now,
    ): array {
        $metadata['detection_note'] = 'Browser-based logging only; OS-level recorders cannot be fully detected or prevented.';

        // Audit P1.6 / Phase 6.
        $session = $examSession;
        $events = is_array($session->violation_events) ? $session->violation_events : [];
        $prevRisk = (string) $session->risk_state;

        $events[] = [
            'event_type' => 'possible_screen_record_attempt',
            'score' => 0,
            'timestamp' => $now->toISOString(),
            'cooldown_applied' => false,
            'action' => 'autosubmit',
        ];

        $this->persistProctoringEvent(
            $session,
            'possible_screen_record_attempt',
            $metadata,
            $severity ?? 2,
            true,
            'autosubmit',
            'locked',
            $now,
        );

        $session->update([
            'last_violation_at' => $now,
            'risk_state' => 'locked',
            'exam_status' => 'flagged_for_review',
        ]);

        $msg = 'Screen recording is not allowed. Your assessment has been submitted for review.';
        $this->dispatchRealtimeNotifications(
            $session,
            'possible_screen_record_attempt',
            $prevRisk,
            'locked',
            (int) ($session->violation_score ?? 0),
            'autosubmit',
            null,
            $msg,
        );

        return [
            'score' => (int) ($session->violation_score ?? 0),
            'risk_state' => 'locked',
            'action' => 'autosubmit',
            'auto_submit' => true,
            'client_message' => $msg,
            'tab_switch_count' => (int) ($session->tab_switch_count ?? 0),
            'proctoring_overlay' => ['active' => false, 'reason' => null, 'message' => null],
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function ingestExternalDisplayRisk(
        ExamSession $examSession,
        array $settings,
        array $metadata,
        ?int $severity,
        bool $flagged,
        Carbon $now,
    ): array {
        if (! filter_var($settings['external_display_detection_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return $this->emptyDecision($examSession);
        }

        // Audit P1.6 / Phase 6.
        $session = $examSession;
        $events = is_array($session->violation_events) ? $session->violation_events : [];
        $prevRisk = (string) $session->risk_state;

        $events[] = [
            'event_type' => 'external_display_risk',
            'score' => 0,
            'timestamp' => $now->toISOString(),
            'cooldown_applied' => false,
            'action' => 'blur',
        ];

        $this->persistProctoringEvent(
            $session,
            'external_display_risk',
            $metadata,
            $severity ?? 2,
            true,
            'blur',
            'suspicious',
            $now,
        );

        $msg = 'External display risk detected. Disconnect it to continue.';
        $session->update([
            'last_violation_at' => $now,
            'risk_state' => 'suspicious',
            'exam_status' => 'flagged_for_review',
            'proctoring_blur_active' => true,
            'proctoring_blur_reason' => 'external_display',
        ]);

        $this->dispatchRealtimeNotifications(
            $session,
            'external_display_risk',
            $prevRisk,
            'suspicious',
            (int) ($session->violation_score ?? 0),
            'blur',
            $msg,
            null,
        );

        return [
            'score' => (int) ($session->violation_score ?? 0),
            'risk_state' => 'suspicious',
            'action' => 'blur',
            'auto_submit' => false,
            'client_message' => $msg,
            'tab_switch_count' => (int) ($session->tab_switch_count ?? 0),
            'proctoring_overlay' => [
                'active' => true,
                'reason' => 'external_display',
                'message' => $msg,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    /**
     * Maximum number of times a student may dismiss the proctoring blur
     * overlay (e.g. via /proctoring-overlay/clear) before we treat further
     * dismissals as evidence of evasion and auto-submit.
     */
    public const MAX_STUDENT_OVERLAY_DISMISSALS = 3;

    private function ingestOverlayResolved(ExamSession $examSession, array $metadata, Carbon $now): array
    {
        $session = $examSession;
        $events = is_array($session->violation_events) ? $session->violation_events : [];
        $reason = isset($metadata['resolved_reason']) ? (string) $metadata['resolved_reason'] : 'student_cleared';

        // Red-team Phase 1 finding H1: a malicious student could spam
        // /proctoring-overlay/clear (or POST a synthetic
        // proctoring_overlay_resolved event) to dismiss the blur after a
        // face-obstruction strike WITHOUT actually fixing the camera.
        // We now cap dismissals: after MAX_STUDENT_OVERLAY_DISMISSALS the
        // session escalates to autosubmit with reason
        // "overlay_dismiss_abuse". The current overlay is NOT cleared in
        // the abuse case so the student cannot continue answering.
        // Architecture Review Phase 3: count past dismissals from
        // proctoring_events instead of walking the JSON buffer. The
        // composite (user_id, quiz_id, event_type, created_at) index
        // makes this a single covered count.
        $previousDismissals = (int) ProctoringEvent::query()
            ->where('user_id', (int) $session->student_id)
            ->where('quiz_id', (int) $session->exam_id)
            ->where('event_type', 'proctoring_overlay_resolved')
            ->count();
        $newDismissalCount = $previousDismissals + 1;
        $abuse = $newDismissalCount > self::MAX_STUDENT_OVERLAY_DISMISSALS;

        $action = $abuse ? 'autosubmit' : 'log';
        $riskState = $abuse ? 'locked' : (string) ($session->risk_state ?? 'normal');

        $events[] = [
            'event_type' => 'proctoring_overlay_resolved',
            'score' => 0,
            'timestamp' => $now->toISOString(),
            'cooldown_applied' => false,
            'action' => $action,
            'payload' => [
                'resolved_reason' => $reason,
                'dismissal_count' => $newDismissalCount,
                'abuse' => $abuse,
            ],
        ];

        $this->persistProctoringEvent(
            $session,
            'proctoring_overlay_resolved',
            $metadata + ['dismissal_count' => $newDismissalCount, 'abuse' => $abuse],
            $abuse ? 4 : 1,
            $abuse,
            $action,
            $riskState,
            $now,
        );

        if ($abuse) {
            $session->update([
                'last_violation_at' => $now,
                'risk_state' => $riskState,
                'exam_status' => 'flagged_for_review',
                'violation_count' => (int) $session->violation_count + 1,
            ]);

            return [
                'score' => (int) ($session->violation_score ?? 0),
                'risk_state' => $riskState,
                'action' => $action,
                'auto_submit' => true,
                'client_message' => 'You have dismissed the camera-check warning too many times. Your assessment has been submitted for review.',
                'tab_switch_count' => (int) ($session->tab_switch_count ?? 0),
                'proctoring_overlay' => [
                    'active' => (bool) ($session->proctoring_blur_active ?? false),
                    'reason' => $session->proctoring_blur_reason,
                    'message' => null,
                ],
            ];
        }

        $session->update([
            'proctoring_blur_active' => false,
            'proctoring_blur_reason' => null,
            'last_violation_at' => $now,
        ]);

        return [
            'score' => (int) ($session->violation_score ?? 0),
            'risk_state' => $riskState,
            'action' => $action,
            'auto_submit' => false,
            'client_message' => null,
            'tab_switch_count' => (int) ($session->tab_switch_count ?? 0),
            'proctoring_overlay' => ['active' => false, 'reason' => null, 'message' => null],
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function ingestLegacyWeightedEvent(
        ExamSession $examSession,
        string $eventType,
        array $settings,
        array $metadata,
        ?int $severity,
        bool $flagged,
        Carbon $now,
    ): array {
        $events = is_array($examSession->violation_events) ? $examSession->violation_events : [];
        $previousRiskState = (string) $examSession->risk_state;

        $cooldownSeconds = (int) ($settings['cooldown_seconds'] ?? 45);
        $inCooldown = $this->isInCooldown($examSession, $eventType, $now, $cooldownSeconds);

        $scoreDelta = $this->resolveScoreDelta($settings, [], $eventType, $inCooldown);
        $newScore = ((int) $examSession->violation_score) + $scoreDelta;
        $riskState = $this->resolveRiskState($settings, $newScore);
        $action = $this->resolveAction($settings, $eventType, [], $newScore, $inCooldown);

        $events[] = [
            'event_type' => $eventType,
            'score' => $scoreDelta,
            'timestamp' => $now->toISOString(),
            'cooldown_applied' => $inCooldown,
            'action' => $action,
        ];

        $this->persistProctoringEvent(
            $examSession,
            $eventType,
            $metadata,
            $severity ?? 1,
            $flagged,
            $action,
            $riskState,
            $now,
        );

        $examSession->update([
            'violation_count' => (int) $examSession->violation_count + ($flagged ? 1 : 0),
            'violation_score' => $newScore,
            'last_violation_at' => $now,
            'risk_state' => $riskState,
            'exam_status' => $newScore >= (int) data_get($settings, 'suspicious_score', 50)
                ? 'flagged_for_review'
                : $examSession->exam_status,
        ]);

        // Audit P1.6: ->update() refreshed attributes in-place; skip the fresh().
        $this->dispatchRealtimeNotifications(
            $examSession,
            $eventType,
            $previousRiskState,
            $riskState,
            $newScore,
            $action,
            null,
            null,
            $newScore - $scoreDelta,
        );

        return [
            'score' => $newScore,
            'risk_state' => $riskState,
            'action' => $action,
            'auto_submit' => $action === 'autosubmit',
            'client_message' => null,
            'tab_switch_count' => (int) ($examSession->tab_switch_count ?? 0),
            'proctoring_overlay' => [
                'active' => (bool) ($examSession->proctoring_blur_active ?? false),
                'reason' => $examSession->proctoring_blur_reason,
                'message' => null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function persistProctoringEvent(
        ExamSession $examSession,
        string $eventType,
        array $metadata,
        int $severity,
        bool $flagged,
        string $actionTaken,
        string $riskState,
        Carbon $now,
    ): void {
        ProctoringEvent::create([
            'user_id' => $examSession->student_id,
            'quiz_id' => $examSession->exam_id,
            'event_type' => $eventType,
            'severity' => $severity,
            'flagged' => $flagged,
            'action_taken' => $actionTaken,
            'metadata' => [
                'session_id' => $examSession->session_id,
                'student_id' => $examSession->student_id,
                'exam_id' => $examSession->exam_id,
                'risk_state' => $riskState,
                'payload' => $metadata,
            ],
            'created_at' => $now,
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function dispatchRealtimeNotifications(
        ExamSession $examSession,
        string $eventType,
        string $previousRiskState,
        string $riskState,
        int $violationScore,
        string $action,
        ?string $overrideWarningMessage,
        ?string $autoSubmitStudentMessage = null,
        ?int $previousViolationScore = null,
    ): void {
        // Audit Phase 9 / P1.5: when the broadcaster is the "log" driver
        // every broadcast() call writes a JSON line to laravel.log. On a
        // 500-student exam that is ~1,200 wasted disk writes per minute.
        // Skip the entire dispatcher when no real broadcaster is wired.
        if ($this->shouldSkipBroadcast()) {
            return;
        }

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

        if ($action === 'warn' || $action === 'blur') {
            $message = $overrideWarningMessage ?? $this->warningMessage($eventType);
            broadcast(new ProctoringWarningEvent(
                sessionId: $examSession->session_id,
                examId: (int) $examSession->exam_id,
                studentId: (int) $examSession->student_id,
                message: $message,
                riskState: $riskState,
                violationScore: $violationScore,
                eventType: $eventType,
            ));
        }

        if ($action === 'autosubmit') {
            $reason = match ($eventType) {
                'tab_switch' => 'tab_switch_limit',
                'phone_detected' => 'phone_detected',
                'multiple_faces' => 'multiple_faces_limit',
                'possible_screenshot_attempt' => 'screenshot_attempt',
                'possible_screen_record_attempt' => 'screen_record_attempt',
                default => 'violation_threshold',
            };
            broadcast(new ExamAutoSubmitEvent(
                sessionId: $examSession->session_id,
                examId: (int) $examSession->exam_id,
                studentId: (int) $examSession->student_id,
                reason: $reason,
                violationScore: $violationScore,
                riskState: $riskState,
            ));

            $heldMsg = $autoSubmitStudentMessage
                ?? $overrideWarningMessage
                ?? 'Your exam has been submitted due to violation detection. Your result is under review. Please contact your lecturer.';

            broadcast(new ExamHeldResultEvent(
                sessionId: $examSession->session_id,
                examId: (int) $examSession->exam_id,
                studentId: (int) $examSession->student_id,
                message: $heldMsg,
                reason: 'submitted_held',
            ));
        } elseif (
            $previousViolationScore !== null
            && $violationScore >= ResultFinalizationService::HOLD_VIOLATION_THRESHOLD
            && $previousViolationScore < ResultFinalizationService::HOLD_VIOLATION_THRESHOLD
        ) {
            // First time the running risk score crosses the hold threshold (60).
            // The student keeps working, but their result will be held for review.
            broadcast(new ExamHeldResultEvent(
                sessionId: $examSession->session_id,
                examId: (int) $examSession->exam_id,
                studentId: (int) $examSession->student_id,
                message: 'Your assessment will continue, but your result has been flagged and will be held for review by your lecturer.',
                reason: 'violation_threshold',
            ));
        }
    }

    private function warningMessage(string $eventType): string
    {
        return match ($eventType) {
            'tab_switch' => 'Please stay on the assessment page. Leaving the page may submit your work.',
            'fullscreen_exit' => 'Fullscreen mode ended. Please restore fullscreen if required by your institution.',
            'face_missing' => 'Your face was not visible to the camera.',
            'multiple_faces' => 'Multiple faces were detected near your workstation.',
            'phone_detected' => 'A mobile device was detected. Your assessment has been submitted for review.',
            'face_covered', 'face_obstructed', 'face_not_clear' => 'Please remove your hand or anything covering your face.',
            'possible_screenshot_attempt' => 'Screenshot attempts are not allowed during this assessment.',
            'possible_screen_record_attempt' => 'Screen recording is not allowed. Your assessment has been submitted for review.',
            'external_display_risk' => 'External display risk detected. Disconnect it to continue.',
            'essay_clipboard_attempt' => 'Clipboard use is restricted during essay answers.',
            'exam_integrity_signal' => 'An exam integrity safeguard was triggered. Please stay within the exam window.',
            default => 'A proctoring concern was detected. Please adjust your setup.',
        };
    }

    private function resolveScoreDelta(array $settings, array $events, string $eventType, bool $inCooldown): int
    {
        if ($inCooldown) {
            return 0;
        }

        if ($eventType === 'essay_clipboard_attempt' && ! $this->examPolicy->isProctoringEnabled()) {
            return 0;
        }

        if ($eventType === 'exam_integrity_signal' && ! $this->examPolicy->isProctoringEnabled()) {
            return 0;
        }

        if ($eventType === 'fullscreen_exit' && empty($settings['fullscreen_enforced'])) {
            return 0;
        }

        if ($eventType === 'phone_detected' && empty($settings['phone_detection_enabled'])) {
            return 0;
        }

        /** @var array<string, int> $weights */
        $weights = is_array($settings['violation_weights'] ?? null)
            ? $settings['violation_weights']
            : self::normalizeViolationWeights([]);

        return (int) ($weights[$eventType] ?? 0);
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function resolveAction(array $settings, string $eventType, array $events, int $newScore, bool $inCooldown): string
    {
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

    /**
     * Audit Phase 9: returns true when broadcasts would be no-ops anyway,
     * so we skip the whole dispatcher (event creation, observers,
     * connection serializing, log line writes).
     */
    private function shouldSkipBroadcast(): bool
    {
        $driver = (string) (config('broadcasting.default') ?? 'null');
        if ($driver === 'null' || $driver === 'log') {
            return true;
        }

        $name = config('broadcasting.connections.'.$driver.'.driver');
        if ($name === 'null' || $name === 'log') {
            return true;
        }

        return false;
    }

    /**
     * Architecture Review Phase 3: cooldown is served by an indexed
     * MAX(created_at) on proctoring_events for (user_id, quiz_id,
     * event_type) — single key seek using the
     * proctoring_events_user_quiz_type_created_idx composite.
     */
    private function isInCooldown(ExamSession $session, string $eventType, Carbon $now, int $cooldownSeconds): bool
    {
        $lastAt = ProctoringEvent::query()
            ->where('user_id', (int) $session->student_id)
            ->where('quiz_id', (int) $session->exam_id)
            ->where('event_type', $eventType)
            ->orderByDesc('id')
            ->value('created_at');

        if ($lastAt === null) {
            return false;
        }

        $lastCarbon = $lastAt instanceof Carbon ? $lastAt : Carbon::parse((string) $lastAt);

        // Carbon 3 returns signed diffs by default, so abs() means
        // "elapsed time" regardless of direction.
        return abs($now->diffInSeconds($lastCarbon)) < $cooldownSeconds;
    }

    /**
     * @param  list<string>  $eventTypes
     */
    private function isInCooldownAny(ExamSession $session, array $eventTypes, Carbon $now, int $cooldownSeconds): bool
    {
        if ($eventTypes === []) {
            return false;
        }

        $lastAt = ProctoringEvent::query()
            ->where('user_id', (int) $session->student_id)
            ->where('quiz_id', (int) $session->exam_id)
            ->whereIn('event_type', $eventTypes)
            ->orderByDesc('id')
            ->value('created_at');

        if ($lastAt === null) {
            return false;
        }

        $lastCarbon = $lastAt instanceof Carbon ? $lastAt : Carbon::parse((string) $lastAt);

        return abs($now->diffInSeconds($lastCarbon)) < $cooldownSeconds;
    }
}
