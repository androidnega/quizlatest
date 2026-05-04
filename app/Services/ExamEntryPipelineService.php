<?php

namespace App\Services;

use App\Models\ExamSession;
use App\Models\Quiz;
use App\Support\FaceEmbeddingComparator;
use App\Support\ProctoringCapabilityResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Single gate for authenticated exam starts (ordering enforced here only).
 */
class ExamEntryPipelineService
{
    public function __construct(
        private readonly ProctoringGlobalControlService $globalControl,
        private readonly SystemSettingsService $systemSettings,
        private readonly SystemExamPolicyService $examPolicy,
        private readonly ExamOtpService $examOtp,
        private readonly ExamRuntimeInfraGate $infraGate,
        private readonly ExamRedisService $examRedis,
    ) {}

    /**
     * @param  array{
     *     exam_id:int,
     *     face_embedding?:array|null,
     *     face_retry_attempt?:int|null,
     *     hardware_concurrency?:int|null,
     *     device_memory_gb?:float|null,
     *     network_effective_type?:string|null,
     *     save_data?:bool|null
     * }  $validated
     * @return array<string, mixed>
     */
    public function execute(Request $request, array $validated): array
    {
        // 1. Authenticated student + basic eligibility
        $student = $request->user();
        abort_unless($student && $student->role === 'student', 403);
        abort_unless($student->class_id !== null, 422, __('student_ui.class_group_not_assigned'));
        abort_unless(! $this->globalControl->blocksExamStarts(), 423, 'Exam entry temporarily unavailable.');

        $examId = (int) $validated['exam_id'];
        $studentId = (int) $student->id;

        $lockHeld = false;
        if (! $this->examRedis->acquireSessionStartLock($studentId, $examId)) {
            abort(409, 'An exam start is already in progress. Please wait a moment.');
        }
        $lockHeld = true;

        try {
            $this->examRedis->enforceExamStartRateLimit($studentId);

            $exam = $this->examRedis->rememberQuiz($examId, fn () => Quiz::query()->findOrFail($examId));

            abort_unless($exam->status === 'published', 422, 'This exam is not available.');
            abort_unless($exam->isAvailableForStudentToStart(now()), 422, 'This exam is outside its scheduled window.');

            // 2. Exam access — class must be enrolled in exam course
            $classHasExamCourse = DB::table('class_course')
                ->where('class_id', $student->class_id)
                ->where('course_id', $exam->course_id)
                ->exists();
            abort_unless($classHasExamCourse, 422, 'Exam is not assigned to your class.');

            // 3. Session rules (before OTP / SMS cost)
            $existingSubmitted = ExamSession::query()
                ->where('student_id', $student->id)
                ->where('exam_id', $exam->id)
                ->where('status', 'submitted')
                ->exists();
            abort_unless(! $existingSubmitted, 422, 'Re-entry is not allowed after submission.');

            $activeSessionExists = ExamSession::query()
                ->where('student_id', $student->id)
                ->whereIn('status', ['active', 'paused'])
                ->exists();
            abort_unless(! $activeSessionExists, 422, 'Another active session already exists.');

            // 4. OTP — backend must be reachable when OTP is enabled
            if ($this->examPolicy->isOtpEnabled()) {
                if (! $this->infraGate->examOtpStorageOperational()) {
                    return [
                        'status' => 'service_unavailable',
                        'message' => 'Verification service temporarily unavailable. Try again.',
                    ];
                }

                try {
                    $otpGate = $this->examOtp->evaluateStartGate($student, $exam->id);
                } catch (RuntimeException) {
                    abort(503, 'SMS verification is temporarily unavailable. Please contact support.');
                }
            } else {
                $otpGate = 'continue';
            }

            $otpTtl = $this->examPolicy->getOtpExpirySeconds();

            if ($otpGate === 'otp_required') {
                return [
                    'status' => 'otp_required',
                    'message' => 'Enter the verification code sent to your phone.',
                    'exam_id' => $exam->id,
                    'expires_in_seconds' => $otpTtl,
                ];
            }

            if ($otpGate === 'otp_pending') {
                return [
                    'status' => 'otp_pending',
                    'message' => 'Enter the verification code sent to your phone, or wait if you just requested it.',
                    'exam_id' => $exam->id,
                    'expires_in_seconds' => $otpTtl,
                ];
            }

            $faceRequired = $this->examPolicy->isProctoringEnabled()
                && $this->examPolicy->isFaceVerificationRequired();

            // 5. Face verification (skipped when disabled by system policy)
            $faceEmbedding = $validated['face_embedding'] ?? null;
            if ($faceRequired) {
                abort_unless(
                    is_array($faceEmbedding) && count($faceEmbedding) >= 3,
                    422,
                    'Face verification required after OTP.',
                );
            }

            // 6. Load exam.proctoring_settings (normalized canonical keys only)
            $defaultsRaw = $this->systemSettings->get('default_proctoring_settings');
            $defaults = [];
            if ($defaultsRaw !== null && $defaultsRaw !== '') {
                try {
                    $decoded = json_decode($defaultsRaw, true, 512, JSON_THROW_ON_ERROR);
                    $defaults = is_array($decoded) ? $decoded : [];
                } catch (\Throwable) {
                    $defaults = [];
                }
            }

            $examOnly = is_array($exam->proctoring_settings) ? $exam->proctoring_settings : [];
            $mergedForNormalize = array_replace_recursive($defaults, $examOnly);

            $normalizedSettings = ProctoringOrchestratorService::normalizeProctoringSettings(
                $mergedForNormalize,
                (int) $exam->id,
            );
            $normalizedSettings = $this->examPolicy->capNormalizedProctoringSettings($normalizedSettings);

            // 7. Apply global orchestrator-facing overrides for visibility (merged copy)
            $effectiveForOrchestrator = $this->globalControl->mergeExamSettingsForOrchestrator(
                ProctoringOrchestratorService::mergeInternalBandsWithNormalized($normalizedSettings),
            );
            $effectiveForOrchestrator = $this->examPolicy->capEffectiveOrchestratorSettings($effectiveForOrchestrator);

            // Strip internal bands from client-facing payload — expose copy without INTERNAL_* leakage patterns
            $clientProctoringSettings = [
                'face_match_threshold' => $effectiveForOrchestrator['face_match_threshold'],
                'tab_switch_rules' => $effectiveForOrchestrator['tab_switch_rules'],
                'phone_detection_enabled' => $effectiveForOrchestrator['phone_detection_enabled'],
                'fullscreen_enforced' => $effectiveForOrchestrator['fullscreen_enforced'],
                'auto_submit_enabled' => $effectiveForOrchestrator['auto_submit_enabled'],
                'violation_weights' => $effectiveForOrchestrator['violation_weights'],
                'cooldown_seconds' => $effectiveForOrchestrator['cooldown_seconds'],
                'auto_submit_score_effective' => (int) ($effectiveForOrchestrator['auto_submit_score'] ?? 90),
            ];
            $clientProctoringSettings = $this->examPolicy->capClientProctoringPayload($clientProctoringSettings);

            // 8. Face verification (threshold only adjusted by governance relax flag — same comparator)
            if ($faceRequired) {
                $threshold = (float) $normalizedSettings['face_match_threshold'];
                if ($this->globalControl->relaxFaceVerification()) {
                    $threshold = max(45.0, $threshold - 10.0);
                }

                $template = is_array($student->face_embedding) ? $student->face_embedding : [];
                $similarity = FaceEmbeddingComparator::similarityPercent($template, $faceEmbedding);
                $retryAttempt = (int) ($validated['face_retry_attempt'] ?? 0);
                abort_unless(! empty($template), 422, 'Face template not enrolled.');
                abort_unless(
                    $similarity >= $threshold || $retryAttempt === 0,
                    422,
                    'Face verification failed. Retry once.',
                );
                abort_unless($similarity >= $threshold, 422, 'Face verification failed. Exam start blocked.');
            } else {
                $similarity = 100.0;
            }

            // 9. Device capability (heuristic; no AI)
            $capabilityHints = [
                'hardware_concurrency' => $validated['hardware_concurrency'] ?? null,
                'device_memory_gb' => $validated['device_memory_gb'] ?? null,
                'network_effective_type' => $validated['network_effective_type'] ?? null,
                'save_data' => $validated['save_data'] ?? null,
            ];
            $performanceProfile = ProctoringCapabilityResolver::resolve($capabilityHints);

            // 10. Initialize exam session
            $session = DB::transaction(function () use ($student, $exam) {
                $existingSubmitted = ExamSession::query()
                    ->where('student_id', $student->id)
                    ->where('exam_id', $exam->id)
                    ->where('status', 'submitted')
                    ->lockForUpdate()
                    ->exists();
                abort_unless(! $existingSubmitted, 422, 'Re-entry is not allowed after submission.');

                $activeSessionExists = ExamSession::query()
                    ->where('student_id', $student->id)
                    ->whereIn('status', ['active', 'paused'])
                    ->lockForUpdate()
                    ->exists();
                abort_unless(! $activeSessionExists, 422, 'Another active session already exists.');

                return ExamSession::create([
                    'student_id' => $student->id,
                    'class_id' => $student->class_id,
                    'exam_id' => $exam->id,
                    'session_id' => (string) Str::uuid(),
                    'status' => 'active',
                    'start_time' => now(),
                    'end_time' => null,
                    'violation_count' => 0,
                    'violation_score' => 0,
                    'violation_events' => [],
                    'last_event_time' => null,
                    'risk_state' => 'normal',
                    'exam_status' => 'active',
                ]);
            });

            $this->examRedis->incrementActiveSessions((int) $exam->id);

            $this->examOtp->forgetVerifiedFlag((int) $student->id, (int) $exam->id);

            // 11. Ready-to-start payload
            return [
                'session_id' => $session->session_id,
                'status' => $session->status,
                'start_time' => $session->start_time?->toISOString(),
                'face_similarity' => $similarity,
                'proctoring_settings_effective' => $clientProctoringSettings,
                'performance_profile' => $performanceProfile,
                'global_control_revision' => (int) ($this->globalControl->getControl()['revision'] ?? 0),
            ];
        } finally {
            if ($lockHeld) {
                $this->examRedis->releaseSessionStartLock($studentId, $examId);
            }
        }
    }
}
