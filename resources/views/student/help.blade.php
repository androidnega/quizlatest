<x-layouts.student>
    <x-slot name="title">{{ __('Help') }}</x-slot>
    <x-slot name="subtitle">{{ __('How assessments work in QuizSnap.') }}</x-slot>

    @php
        $helpItems = [
            [
                'key' => 'continue',
                'icon' => 'fa-play',
                'title' => __('Starting an assessment'),
                'body' => __('Open Assessments, choose your work, then follow Prepare. For timed quizzes, the timer starts after you enter the attempt.'),
            ],
            [
                'key' => 'assignments_due',
                'icon' => 'fa-file-pen',
                'title' => __('Assignments'),
                'bullets' => [
                    __('Typed response: write in the editor. You may add an optional or required file.'),
                    __('Required attachment: upload before submit is accepted.'),
                    __('File only: follow on-screen instructions when there is no text box.'),
                ],
            ],
            [
                'key' => 'active_now',
                'icon' => 'fa-copy',
                'title' => __('Copy and paste'),
                'body' => __('Sometimes paste is disabled. If you see a notice, type your answer directly.'),
            ],
            [
                'key' => 'submitted_work',
                'icon' => 'fa-shield-halved',
                'title' => __('Proctoring & integrity'),
                'bullets' => [
                    __('Your school may require camera, microphone, or tab-focus checks.'),
                    __('Screenshot and display checks are best-effort only.'),
                    __('Integrity flags do not automatically change your marks.'),
                ],
            ],
            [
                'key' => 'results_released',
                'icon' => 'fa-square-poll-vertical',
                'title' => __('Results & feedback'),
                'body' => __('Scores appear after your examiner releases them. “Under review” means your work is still being processed.'),
            ],
            [
                'key' => 'closed_missed',
                'icon' => 'fa-circle-info',
                'title' => __('If something goes wrong'),
                'body' => __('Wrong class details: contact your coordinator. Marking questions: your examiner. Technical issues: school IT.'),
            ],
        ];
    @endphp

    <div class="space-y-5 pb-6">
        <ul class="qs-wl-list qs-wl-list--shimmer">
            @foreach ($helpItems as $i => $item)
                <li class="qs-wl-item qs-wl-item--{{ $item['key'] }} qs-help-item" style="--card-i: {{ $i }}">
                    <div class="qs-wl-item__head">
                        <h3 class="qs-wl-item__title">{{ $item['title'] }}</h3>
                        <span class="qs-wl-item__icon" aria-hidden="true">
                            <i class="fa-solid {{ $item['icon'] }}"></i>
                        </span>
                    </div>

                    @if (! empty($item['body']))
                        <p class="qs-help-item__body">{{ $item['body'] }}</p>
                    @endif

                    @if (! empty($item['bullets']))
                        <ul class="qs-help-item__bullets">
                            @foreach ($item['bullets'] as $bullet)
                                <li>{{ $bullet }}</li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</x-layouts.student>
