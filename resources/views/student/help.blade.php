<x-layouts.student>
    <x-slot name="title">{{ __('Help & instructions') }}</x-slot>
    <x-slot name="subtitle">{{ __('Plain-language guide for assessments in QuizSnap.') }}</x-slot>

    <div class="mx-auto max-w-2xl space-y-4 pb-8 text-slate-900">
        <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
            <h2 class="text-sm font-semibold text-slate-900">{{ __('Starting an assessment') }}</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                {{ __('Open your dashboard work list, choose the assessment, then follow Prepare. When the window is open, you can start. For timed quizzes and exams, the timer begins after you enter the attempt.') }}
            </p>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
            <h2 class="text-sm font-semibold text-slate-900">{{ __('Assignments') }}</h2>
            <ul class="mt-2 list-inside list-disc space-y-2 text-sm text-slate-600">
                <li>{{ __('Typed response: write your answer in the box. You may be able to add an optional or required file, depending on what your examiner set.') }}</li>
                <li>{{ __('Optional attachment: you can submit with text only.') }}</li>
                <li>{{ __('Required attachment: choose a file before submit is accepted.') }}</li>
                <li>{{ __('File only: follow the on-screen instructions; there may be no long text box.') }}</li>
            </ul>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
            <h2 class="text-sm font-semibold text-slate-900">{{ __('Copy and paste') }}</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                {{ __('Sometimes paste is turned off to reduce unfair copying. If you see a notice, use your own words in the editor.') }}
            </p>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
            <h2 class="text-sm font-semibold text-slate-900">{{ __('Proctoring & integrity') }}</h2>
            <ul class="mt-2 list-inside list-disc space-y-2 text-sm text-slate-600">
                <li>{{ __('Your school may require camera, microphone, tab focus checks, and similar signals. These help your examiner review fairness; they are not a guarantee that every rule break is detected.') }}</li>
                <li>{{ __('Screenshot and external display checks are best-effort only — they are not perfect.') }}</li>
                <li>{{ __('Tab-switch and phone-detection rules depend on your institution’s settings.') }}</li>
                <li>{{ __('Face visibility may be required so identity checks can run.') }}</li>
                <li>{{ __('Integrity flags and proctoring events do not automatically change your marks. Your examiner decides outcomes.') }}</li>
            </ul>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
            <h2 class="text-sm font-semibold text-slate-900">{{ __('Results & feedback') }}</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                {{ __('Scores and written feedback appear only after your examiner releases them (and for some assignments, after grades are released to the class). If you see “under review” or “awaiting grading”, your work is still being processed.') }}
            </p>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
            <h2 class="text-sm font-semibold text-slate-900">{{ __('If something goes wrong') }}</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                {{ __('For wrong class or programme details, contact your coordinator. For marking questions, contact your course examiner. For technical issues, contact your school IT or support channel.') }}
            </p>
        </section>
    </div>
</x-layouts.student>
