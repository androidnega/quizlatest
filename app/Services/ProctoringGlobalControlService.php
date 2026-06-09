<?php

namespace App\Services;

use App\Events\ExamGovernanceUpdatedEvent;
use App\Models\ExamSession;
use Illuminate\Support\Facades\Storage;

/**
 * Central admin governance for proctoring. Mutations MUST go through this service.
 *
 * Persistence: storage/app/private/proctoring_global_control.json (no new migrations).
 */
class ProctoringGlobalControlService
{
    private const STORAGE_PATH = 'private/proctoring_global_control.json';

    /** @return array<string, mixed> */
    public function getControl(): array
    {
        $merged = array_replace_recursive($this->defaults(), $this->readFromDisk());
        unset($merged['relax_face_verification']);

        return $merged;
    }

    /**
     * Apply partial patch and persist. Returns merged control record.
     *
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    public function applyPatch(array $patch): array
    {
        unset($patch['revision'], $patch['updated_at']);

        $current = $this->getControl();
        $merged = array_replace_recursive($current, array_intersect_key($patch, array_flip(array_keys($this->defaults()))));
        $merged['revision'] = (int) ($merged['revision'] ?? 0) + 1;
        $merged['updated_at'] = now()->toISOString();

        $this->writeToDisk($merged);

        return $merged;
    }

    public function emergencyShutdown(bool $activate): array
    {
        $patch = ['emergency_shutdown' => $activate];
        $merged = $this->applyPatch($patch);

        if ($activate) {
            ExamSession::query()
                ->whereIn('status', ['active', 'paused'])
                ->update([
                    'risk_state' => 'locked',
                    'exam_status' => 'locked_by_admin',
                ]);
        }

        $this->broadcastSnapshot($merged);

        return $merged;
    }

    /**
     * Merge exam-normalized settings with global orchestrator overrides (numeric/key swaps only).
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function mergeExamSettingsForOrchestrator(array $settings): array
    {
        $g = $this->getControl();

        if (! empty($g['disable_phone_detection_globally'])) {
            $settings['phone_detection_enabled'] = false;
        }

        if (array_key_exists('auto_submit_score_override', $g) && $g['auto_submit_score_override'] !== null && $g['auto_submit_score_override'] !== '') {
            $settings['auto_submit_score'] = max(30, min(200, (int) $g['auto_submit_score_override']));
        }

        return $settings;
    }

    public function shouldBypassProctoringIngest(): bool
    {
        $g = $this->getControl();

        return empty($g['modules_enabled']) || ! empty($g['emergency_shutdown']);
    }

    public function blocksExamStarts(): bool
    {
        $g = $this->getControl();

        return empty($g['modules_enabled']) || ! empty($g['emergency_shutdown']);
    }

    /** @param  array<string, mixed>  $snapshot */
    public function broadcastSnapshot(array $snapshot): void
    {
        // Audit Phase 9 / P1.5: when broadcasting is the "log" or "null"
        // driver (the shared-hosting default) iterating every active session
        // to fire useless events would write thousands of log lines per
        // governance toggle.
        $driver = (string) (config('broadcasting.default') ?? 'null');
        if ($driver === 'null' || $driver === 'log') {
            return;
        }

        ExamSession::query()
            ->whereIn('status', ['active', 'paused'])
            ->cursor()
            ->each(function (ExamSession $session) use ($snapshot): void {
                broadcast(new ExamGovernanceUpdatedEvent(
                    sessionId: $session->session_id,
                    snapshot: $snapshot,
                ));
            });
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        return [
            'revision' => 0,
            'updated_at' => null,
            'modules_enabled' => true,
            'emergency_shutdown' => false,
            'disable_phone_detection_globally' => false,
            'auto_submit_score_override' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function readFromDisk(): array
    {
        if (! Storage::disk('local')->exists(self::STORAGE_PATH)) {
            return [];
        }

        $json = Storage::disk('local')->get(self::STORAGE_PATH);
        $data = json_decode((string) $json, true);

        return is_array($data) ? $data : [];
    }

    /** @param  array<string, mixed>  $data */
    private function writeToDisk(array $data): void
    {
        Storage::disk('local')->makeDirectory(dirname(self::STORAGE_PATH));
        Storage::disk('local')->put(self::STORAGE_PATH, json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
}
