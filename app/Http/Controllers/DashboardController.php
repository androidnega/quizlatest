<?php

namespace App\Http\Controllers;

use App\Models\ExamSession;
use App\Models\Quiz;
use App\Models\Result;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $user = auth()->user();

        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        if ($user->role === 'coordinator') {
            return redirect()->route('coordinator.dashboard');
        }

        if ($user->role !== 'student') {
            return view('dashboard', [
                'user' => $user,
                'stats' => [],
            ]);
        }

        return view('student.dashboard', $this->buildStudentDashboardData($user));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStudentDashboardData(User $user): array
    {
        $now = Carbon::now();

        $activeSession = ExamSession::query()
            ->where('student_id', $user->id)
            ->whereIn('status', ['active', 'paused'])
            ->with(['exam.course:id,code,title'])
            ->first();

        $courseIds = collect();
        if ($user->class_id !== null) {
            $courseIds = DB::table('class_course')
                ->where('class_id', $user->class_id)
                ->pluck('course_id');
        }

        $publishedExams = collect();
        if ($courseIds->isNotEmpty()) {
            $publishedExams = Quiz::query()
                ->whereIn('course_id', $courseIds)
                ->where('status', 'published')
                ->where('university_id', $user->university_id)
                ->with(['course:id,code,title'])
                ->orderBy('available_from')
                ->orderBy('title')
                ->get();
        }

        $submittedExamIds = ExamSession::query()
            ->where('student_id', $user->id)
            ->where('status', 'submitted')
            ->pluck('exam_id');

        $availableNow = collect();
        $upcoming = collect();

        foreach ($publishedExams as $exam) {
            if ($submittedExamIds->contains($exam->id)) {
                continue;
            }

            if ($activeSession !== null && (int) $activeSession->exam_id !== (int) $exam->id) {
                continue;
            }

            $from = $exam->available_from;
            $to = $exam->available_to;

            if ($from !== null && $now->lt($from)) {
                $upcoming->push($exam);

                continue;
            }

            if ($to !== null && $now->gt($to)) {
                continue;
            }

            $availableNow->push($exam);
        }

        $gradedCount = Result::query()
            ->where('user_id', $user->id)
            ->where('status', 'graded')
            ->count();

        $heldResults = Result::query()
            ->where('user_id', $user->id)
            ->where('status', 'held')
            ->with(['quiz:id,title'])
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $pendingManual = Result::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending_manual')
            ->with(['quiz:id,title'])
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $faceReady = is_array($user->face_embedding) && count($user->face_embedding) >= 3;

        return [
            'user' => $user,
            'activeSession' => $activeSession,
            'availableExams' => $availableNow,
            'upcomingExams' => $upcoming,
            'gradedResultsCount' => $gradedCount,
            'heldResults' => $heldResults,
            'pendingManualResults' => $pendingManual,
            'faceProfileReady' => $faceReady,
            'hasClass' => $user->class_id !== null,
        ];
    }
}
