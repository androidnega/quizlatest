{{--
  Client-side desktop gate for the exam runtime.

  This partial MUST be included as the first child of <body> on every
  exam-taking page so it runs before any other JS bootstraps. It uses
  feature/touch detection rather than UA sniffing, so it correctly
  blocks mobile devices even when the user has switched their browser
  into "Request Desktop Site" mode.

  Detection (all must look mobile-class):
    - pointer:coarse  (touch-only primary input)
    - any-pointer:none of fine  (no mouse / trackpad / stylus)
    - max screen dimension < 1024px
    - touch points > 0
  ANY two of those signals + UA-Data .mobile === true is also a hard block.

  When triggered, the guard:
    1. Hides any exam content (display:none on <body> children).
    2. Renders an inline lockout panel.
    3. Throws so subsequent <script type="module"> initialisations fail
       fast — no socket connections, no camera prompts, no heartbeat.
--}}
<div id="qs-desktop-required-overlay"
     hidden
     style="position:fixed;inset:0;z-index:2147483647;display:none;background:#fff;color:#0f3a3e;padding:1.5rem;font-family:Inter,ui-sans-serif,system-ui,Segoe UI,Roboto,Helvetica Neue,Arial,sans-serif;-webkit-font-smoothing:antialiased;overflow-y:auto;">
    <div style="max-width:520px;margin:max(2rem,8vh) auto 0;text-align:center;">
        <div aria-hidden="true"
             style="display:inline-flex;align-items:center;justify-content:center;width:64px;height:64px;border-radius:16px;background:rgba(86,174,187,0.12);color:#0f3a3e;margin-bottom:1.25rem;font-size:1.6rem;">
            <span style="display:inline-block;transform:translateY(-1px);">&#x1F5A5;&#xFE0F;</span>
        </div>
        <p style="margin:0 0 0.5rem;font-size:0.7rem;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:rgba(15,52,58,0.55);">
            {{ __('Desktop required') }}
        </p>
        <h1 style="margin:0 0 0.85rem;font-size:1.5rem;line-height:1.2;font-weight:600;letter-spacing:-0.01em;color:#0f3a3e;">
            {{ __('Quizzes are desktop-only for now') }}
        </h1>
        <p style="margin:0 0 1.5rem;font-size:0.95rem;line-height:1.55;color:rgba(15,52,58,0.65);">
            {{ __('QuizSnap exams can only be taken on a desktop or laptop computer. The mobile experience is on the way — until then, please switch to a desktop to continue.') }}
        </p>
        <div style="display:flex;flex-wrap:wrap;justify-content:center;gap:0.6rem;">
            <a href="{{ auth()->check() ? route('dashboard') : url('/') }}"
               style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.7rem 1.1rem;border-radius:10px;background:#0f3a3e;color:#fff;font-weight:600;font-size:0.85rem;text-decoration:none;">
                {{ auth()->check() ? __('Back to dashboard') : __('Back to home') }}
            </a>
        </div>
        <p style="margin:1.75rem 0 0;font-size:0.75rem;color:rgba(15,52,58,0.5);">
            {{ __('Tip: the rest of QuizSnap (your dashboard, results, classes) works fine on mobile — only the quiz attempt itself is locked to desktop.') }}
        </p>
    </div>
</div>
<script>
(function () {
    try {
        var w = window.innerWidth || document.documentElement.clientWidth || 0;
        var h = window.innerHeight || document.documentElement.clientHeight || 0;
        var smallestSide = Math.min(w, h);
        var largestSide  = Math.max(w, h);

        var coarse = false;
        var noFinePointer = false;
        try {
            coarse = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
            noFinePointer = window.matchMedia && !window.matchMedia('(any-pointer: fine)').matches;
        } catch (_) {}

        var touchPoints = (navigator.maxTouchPoints || 0) > 1;
        var hasTouchEvents = ('ontouchstart' in window);
        var uaDataMobile = !!(navigator.userAgentData && navigator.userAgentData.mobile);

        // Two independent vectors: hardware says mobile, OR window is too narrow even for desktop layout.
        var hardwareIsMobile =
            (coarse && noFinePointer) ||
            uaDataMobile ||
            ((coarse || hasTouchEvents) && touchPoints && largestSide < 1280);

        var screenIsTooSmall = largestSide < 1024 || smallestSide < 600;

        if (hardwareIsMobile || screenIsTooSmall) {
            // Stop any further bootstrapping. Hide the original page chrome
            // before module scripts get a chance to run camera prompts etc.
            try { document.documentElement.style.overflow = 'hidden'; } catch (_) {}
            try {
                Array.prototype.forEach.call(document.body.children, function (el) {
                    if (el && el.id !== 'qs-desktop-required-overlay') {
                        el.style.display = 'none';
                    }
                });
            } catch (_) {}
            var overlay = document.getElementById('qs-desktop-required-overlay');
            if (overlay) {
                overlay.hidden = false;
                overlay.style.display = 'block';
            }
            // Hard-cancel any module scripts that haven't run yet.
            window.QS_DESKTOP_LOCKED = true;
            // Throw so subsequent inline scripts in the SAME synchronous phase abort.
            throw new Error('QS_DESKTOP_LOCKED');
        }
    } catch (e) {
        if (e && e.message === 'QS_DESKTOP_LOCKED') {
            throw e;
        }
        // Detection failed for some reason — do not lock the user out
        // on a false positive; the server-side UA gate remains the
        // primary defence.
    }
})();
</script>
