<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\PracticeAttempt;
use App\Models\PracticeQuiz;
use App\Services\PracticeModuleSettings;
use Illuminate\Contracts\View\View;

class StudentRevisionSelfCheckController extends Controller
{
    public function show(PracticeModuleSettings $practice): View
    {
        $user = auth()->user();

        $practiceEnabled = $practice->studentPracticeEnabled();
        $materialUploadsEnabled = $practice->courseMaterialUploadsEnabled();
        $aiSummaryEnabled = $practice->aiSummaryEnabled();
        $aiQuizEnabled = $practice->aiPracticeQuizGenerationEnabled();

        $recentScores = collect();
        $quizCount = 0;
        if ($practiceEnabled) {
            $recentScores = PracticeAttempt::query()
                ->where('student_id', $user->id)
                ->whereNotNull('submitted_at')
                ->with(['practiceQuiz.course:id,code,title'])
                ->orderByDesc('submitted_at')
                ->limit(5)
                ->get();

            $quizCount = PracticeQuiz::query()->where('student_id', $user->id)->count();
        }

        return view('student.practice.revision', [
            'practiceEnabled' => $practiceEnabled,
            'materialUploadsEnabled' => $materialUploadsEnabled,
            'aiSummaryEnabled' => $aiSummaryEnabled,
            'aiQuizEnabled' => $aiQuizEnabled,
            'recentScores' => $recentScores,
            'quizCount' => $quizCount,
        ]);
    }
}
