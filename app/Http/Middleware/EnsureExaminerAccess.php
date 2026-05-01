<?php

namespace App\Http\Middleware;

use App\Models\ExaminerCourseAssignment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureExaminerAccess
{
    /**
     * Coordinators with at least one active examiner course assignment.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'coordinator', Response::HTTP_FORBIDDEN);

        $hasAssignment = ExaminerCourseAssignment::query()
            ->where('examiner_user_id', $user->id)
            ->where('is_active', true)
            ->exists();

        abort_unless($hasAssignment, Response::HTTP_FORBIDDEN, 'Exam management requires an active course assignment.');

        return $next($request);
    }
}
