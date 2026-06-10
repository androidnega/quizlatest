<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lock the quiz/exam runtime to desktop browsers.
 *
 * QuizSnap's exam runtime UI is currently desktop-only. Until the
 * mobile attempt experience is built, we deny mobile devices from
 * reaching ANY taking surface — instructions, prepare, take, the
 * AJAX runtime endpoints, and the practice attempt page.
 *
 * Two layers, because either alone is bypassable:
 *   1. Server-side User-Agent regex (this middleware). Catches the
 *      common case of a student opening an exam link directly on a
 *      phone or tablet.
 *   2. Client-side feature detection (see the desktop-only-guard
 *      blade partial). Catches "Request Desktop Site" / desktop-mode
 *      toggles where the UA is masqueraded but the device is still
 *      a touch-only / narrow-screen mobile.
 *
 * For navigational requests we render a clean branded page; for AJAX
 * / fetch endpoints we return JSON 423 Locked so the runtime client
 * can short-circuit gracefully without trying to parse HTML.
 */
class EnsureDesktopForExam
{
    public function handle(Request $request, Closure $next): Response
    {
        $userAgent = (string) ($request->userAgent() ?? '');

        if ($userAgent !== '' && $this->isLikelyMobileUserAgent($userAgent)) {
            return $this->blockedResponse($request);
        }

        return $next($request);
    }

    /**
     * Conservative phone/tablet detection.
     *
     * - Phones (all OSes) match.
     * - Android tablets (no "Mobile" token) and iPads (often UA-spoofed
     *   as macOS in iPadOS 13+) intentionally do NOT match here — the
     *   client-side guard will catch touch-only devices via
     *   `pointer: coarse` and screen-width checks. This keeps server
     *   responses correct for the common phone case while letting JS
     *   handle the spoofed/edge cases.
     */
    protected function isLikelyMobileUserAgent(string $ua): bool
    {
        // Phone-class signals — every common phone OS / browser combo
        // emits at least one of these tokens. We deliberately avoid
        // matching "iPad" and bare "Android" (without the "Mobile"
        // suffix) so generic tablets aren't blocked at the server
        // layer; the client-side guard catches touch-only tablets via
        // pointer/touch checks.
        if (preg_match('#(iPhone|iPod|webOS|BlackBerry|IEMobile|Opera Mini)#i', $ua)) {
            return true;
        }

        // Android phones always include the "Mobile" token.
        if (preg_match('#Android#i', $ua) && preg_match('#Mobile#i', $ua)) {
            return true;
        }

        // Generic phone hint — Windows Phone, KaiOS, etc.
        if (preg_match('#Phone#i', $ua) && ! preg_match('#Macintosh|Windows NT#i', $ua)) {
            return true;
        }

        return false;
    }

    protected function blockedResponse(Request $request): Response
    {
        $message = 'QuizSnap exams can only be taken on a desktop or laptop computer right now. The mobile experience is on the way.';

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'error' => 'desktop_required',
                'message' => $message,
            ], 423);
        }

        return response()->view('desktop-required', [
            'message' => $message,
        ], 423);
    }
}
