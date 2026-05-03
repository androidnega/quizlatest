<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\PracticeAttempt;
use App\Models\PracticeQuiz;
use App\Services\PracticeModuleSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class StudentPracticeHubController extends Controller
{
    public function index(PracticeModuleSettings $practice): View|RedirectResponse
    {
        $practice->assertStudentPracticeOrAbort();

        $user = auth()->user();

        $recentScores = PracticeAttempt::query()
            ->where('student_id', $user->id)
            ->whereNotNull('submitted_at')
            ->with(['practiceQuiz.course:id,code,title'])
            ->orderByDesc('submitted_at')
            ->limit(5)
            ->get();

        $quizCount = PracticeQuiz::query()->where('student_id', $user->id)->count();

        return view('student.practice.index', [
            'recentScores' => $recentScores,
            'quizCount' => $quizCount,
        ]);
    }
}
