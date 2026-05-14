<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ExamSession;
use App\Services\ExamRuntimeInfraGate;
use App\Services\SystemExamPolicyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentExamController extends Controller
{
    public function take(Request $request, ExamSession $examSession): View|RedirectResponse
    {
        abort_unless($request->user()?->role === 'student', 403);
        abort_unless((int) $examSession->student_id === (int) $request->user()->id, 403);

        $user = $request->user();
        if (! $user->is_active) {
            return redirect()->route('login')->withErrors([
                'index_number' => __('Your student account is not active. Please contact your coordinator.'),
            ]);
        }

        if ($user->student_onboarded_at === null) {
            return redirect()->route('login')->withErrors([
                'index_number' => __('Please complete your student onboarding before starting an exam.'),
            ]);
        }

        $gate = app(ExamRuntimeInfraGate::class);
        $examPolicy = app(SystemExamPolicyService::class);

        $examSession->loadMissing(['exam.course']);

        $exam = $examSession->exam;
        $isAssignmentMode = $exam?->isAssignment() ?? false;
        $requireCameraMonitoring = $examPolicy->isCameraMonitoringRequiredForQuiz($exam);
        $assignmentClipboardBlock = $isAssignmentMode
            && (bool) ($exam?->assignment_disable_paste ?? true);

        $examClipboardLock = ! $isAssignmentMode && $examPolicy->isExamClipboardLockEnabled();
        $examScreenshotMitigation = ! $isAssignmentMode && $examPolicy->isExamScreenshotMitigationEnabled();
        $examScreenRecordMitigation = ! $isAssignmentMode && $examPolicy->isExamScreenRecordMitigationEnabled();

        return view('student.exam.take', [
            'examSession' => $examSession,
            'enableLiveSockets' => $gate->enableLiveSockets(),
            'allowPollingFallback' => $gate->allowPollingFallback(),
            'requireCameraMonitoring' => $requireCameraMonitoring,
            'isAssignmentMode' => $isAssignmentMode,
            'assignmentClipboardBlock' => $assignmentClipboardBlock,
            'assignmentAllowsFiles' => $isAssignmentMode && (bool) ($exam?->assignment_allows_files ?? false),
            'assignmentAttachmentRequired' => $isAssignmentMode && (bool) ($exam?->assignment_attachment_required ?? false),
            'examClipboardLock' => $examClipboardLock,
            'examScreenshotMitigation' => $examScreenshotMitigation,
            'examScreenRecordMitigation' => $examScreenRecordMitigation,
            'documentTitle' => $isAssignmentMode ? __('Assignment') : __('Exam'),
        ]);
    }
}
