<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            return $next($request);
        }

        abort(Response::HTTP_FORBIDDEN, 'You are not allowed to access examiner tools.');
    }

    public static function mayAccessExaminerPortal(User $user): bool
    {
        return $user->role === 'examiner';
    }
}
