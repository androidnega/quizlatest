<?php

namespace App\Support;

/**
 * Maps lightweight client hints to an adaptive proctoring profile (no server-side sensors).
 */
final class ProctoringCapabilityResolver
{
    /**
     * @param  array{hardware_concurrency?:int|null,device_memory_gb?:float|null,network_effective_type?:string|null,save_data?:bool|null}  $hints
     * @return array{level:int,face_interval_ms:int,phone_interval_ms:int|null,enable_pose_landmarker:bool,enable_coco_ssd:bool}
     */
    public static function resolve(array $hints): array
    {
        $cores = (int) ($hints['hardware_concurrency'] ?? 0);
        $memoryGb = isset($hints['device_memory_gb']) ? (float) $hints['device_memory_gb'] : null;
        $net = strtolower((string) ($hints['network_effective_type'] ?? ''));
        $saveData = (bool) ($hints['save_data'] ?? false);

        $slowNet = in_array($net, ['slow-2g', '2g'], true);
        $midNet = $net === '3g';

        $level = 1;
        if ($saveData || $slowNet || ($cores > 0 && $cores <= 2) || ($memoryGb !== null && $memoryGb <= 4)) {
            $level = 3;
        } elseif ($midNet || ($cores > 0 && $cores <= 4) || ($memoryGb !== null && $memoryGb <= 8)) {
            $level = 2;
        }

        return match ($level) {
            1 => [
                'level' => 1,
                'face_interval_ms' => 32000,
                'phone_interval_ms' => 45000,
                'enable_pose_landmarker' => true,
                'enable_coco_ssd' => true,
            ],
            2 => [
                'level' => 2,
                'face_interval_ms' => 42000,
                'phone_interval_ms' => 60000,
                'enable_pose_landmarker' => true,
                'enable_coco_ssd' => true,
            ],
            default => [
                'level' => 3,
                'face_interval_ms' => 60000,
                'phone_interval_ms' => null,
                'enable_pose_landmarker' => false,
                'enable_coco_ssd' => false,
            ],
        };
    }
}
