<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\ExamSession;
use App\Models\Quiz;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StudentAssignmentsController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'student', 403);

        $user->loadMissing(['classroom']);

        $now = Carbon::now();
        $rows = collect();
        $activeSession = null;

        if ($user->class_id !== null) {
            $activeSession = ExamSession::query()
                ->where('student_id', $user->id)
                ->whereIn('status', ['active', 'paused'])
                ->with(['exam:id,title,course_id'])
                ->first();

            $courseIds = DB::table('class_course')
                ->where('class_id', $user->class_id)
                ->pluck('course_id');

            if ($courseIds->isNotEmpty()) {
                $courses = Course::query()
                    ->whereIn('id', $courseIds)
                    ->orderBy('code')
                    ->get();

                $submittedExamIds = ExamSession::query()
                    ->where('student_id', $user->id)
                    ->where('status', 'submitted')
                    ->pluck('exam_id');

                foreach ($courses as $course) {
                    $exams = Quiz::query()
                        ->where('course_id', $course->id)
                        ->where('status', 'published')
                        ->where('assessment_type', 'assignment')
                        ->where('university_id', $user->university_id)
                        ->where(function ($q) use ($user) {
                            $q->whereDoesntHave('targetClassrooms')
                                ->orWhereHas('targetClassrooms', function ($q2) use ($user) {
                                    $q2->where('classes.id', (int) $user->class_id);
                                });
                        })
                        ->orderBy('start_time')
                        ->orderBy('title')
                        ->get();

                    $open = collect();
                    $upcoming = collect();
                    foreach ($exams as $exam) {
                        if ($submittedExamIds->contains($exam->id)) {
                            continue;
                        }
                        if ($activeSession !== null && (int) $activeSession->exam_id !== (int) $exam->id) {
                            continue;
                        }
                        $from = $exam->start_time;
                        $to = $exam->end_time;
                        if ($from !== null && $now->lt($from)) {
                            $upcoming->push($exam);

                            continue;
                        }
                        if ($to !== null && $now->gt($to)) {
                            continue;
                        }
                        $open->push($exam);
                    }

                    $rows->push([
                        'course' => $course,
                        'open_exams' => $open->values(),
                        'upcoming_exams' => $upcoming->values(),
                    ]);
                }
            }
        }

        $summaryCourses = $rows->count();
        $summaryOpen = (int) $rows->sum(fn (array $r) => $r['open_exams']->count());
        $summaryUpcoming = (int) $rows->sum(fn (array $r) => $r['upcoming_exams']->count());

        return view('student.assignments.index', [
            'user' => $user,
            'assignments' => $rows,
            'activeSession' => $activeSession,
            'summaryCourses' => $summaryCourses,
            'summaryOpen' => $summaryOpen,
            'summaryUpcoming' => $summaryUpcoming,
        ]);
    }
}
