<?php

namespace App\Http\Middleware;

use App\Models\Quiz;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsExaminer
{
    /**
     * Examiner portal: dedicated examiner role, active course assignments, coordinator with dept access, or examiner permission on an attached role.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(Response::HTTP_FORBIDDEN, 'Authentication required.');
        }

        if (self::mayAccessExaminerPortal($user)) {
            $now = now();
            $cutoffDelete = $now->copy()->subDays(14);
            $deletedDraftCount = Quiz::query()
                ->where('created_by', $user->id)
                ->where('status', 'draft')
                ->where('created_at', '<=', $cutoffDelete)
                ->delete();

            $alerts = Quiz::query()
                ->where('created_by', $user->id)
                ->where('status', 'draft')
                ->where('created_at', '<=', $now->copy()->subDays(3))
                ->orderBy('created_at')
                ->get(['id', 'title', 'created_at'])
                ->map(function (Quiz $quiz) use ($now): array {
                    $ageDays = (int) $quiz->created_at->diffInDays($now);
                    $remaining = max(0, 14 - $ageDays);

                    return [
                        'id' => $quiz->id,
                        'title' => (string) $quiz->title,
                        'age_days' => $ageDays,
                        'remaining_days' => $remaining,
                        'urgent' => $ageDays >= 7,
                    ];
                })
                ->values()
                ->all();

            View::share('examinerDraftAlerts', $alerts);
            View::share('examinerDraftDeletedCount', $deletedDraftCount);

            return $next($request);
        }

        abort(Response::HTTP_FORBIDDEN, 'You are not allowed to access examiner tools.');
    }

    public static function mayAccessExaminerPortal(User $user): bool
    {
        return $user->role === 'examiner';
    }
}
