<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProctoringGlobalControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProctoringGovernanceController extends Controller
{
    public function __construct(
        private readonly ProctoringGlobalControlService $globalControl,
    ) {}

    public function toggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'modules_enabled' => ['sometimes', 'boolean'],
            'disable_phone_detection_globally' => ['sometimes', 'boolean'],
        ]);

        if ($validated === []) {
            return response()->json(['control' => $this->globalControl->getControl()]);
        }

        $merged = $this->globalControl->applyPatch($validated);
        $this->globalControl->broadcastSnapshot($merged);

        return response()->json(['control' => $merged]);
    }

    public function emergencyShutdown(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'activate' => ['required', 'boolean'],
        ]);

        $merged = $this->globalControl->emergencyShutdown($validated['activate']);

        return response()->json(['control' => $merged]);
    }

    public function overrideConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'relax_face_verification' => ['sometimes', 'boolean'],
            'auto_submit_score_override' => ['sometimes', 'nullable', 'integer', 'min:30', 'max:200'],
        ]);

        if ($validated === []) {
            return response()->json(['control' => $this->globalControl->getControl()]);
        }

        $merged = $this->globalControl->applyPatch($validated);
        $this->globalControl->broadcastSnapshot($merged);

        return response()->json(['control' => $merged]);
    }
}
