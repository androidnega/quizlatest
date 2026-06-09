@php
    $extractLetters = static function (?string $formatted): array {
        if ($formatted === null || trim($formatted) === '') {
            return [];
        }

        $letters = [];
        foreach (preg_split('/\r\n|\r|\n/', $formatted) as $line) {
            $line = trim($line);
            if ($line !== '' && preg_match('/^([A-Z])\./', $line, $m)) {
                $letters[] = $m[1];
            }
        }

        return array_values(array_unique($letters));
    };
@endphp

<p class="border-b border-[#E8EDF3] bg-[#F7F8FB] px-4 py-2.5 text-xs text-[#667085] sm:px-6">
    <span class="font-semibold text-[#344054]">{{ __('Key') }}:</span>
    <span class="qs-result-q-tag qs-result-q-tag--you-right ml-2">{{ __('Your pick — correct') }}</span>
    <span class="qs-result-q-tag qs-result-q-tag--wrong ml-1.5">{{ __('Your pick — wrong') }}</span>
    @if ($showCorrectSummaries)
        <span class="qs-result-q-tag qs-result-q-tag--correct ml-1.5">{{ __('Correct answer') }}</span>
    @endif
</p>

<ol class="qs-result-q-list" aria-label="{{ __('Question breakdown') }}">
    @foreach ($breakdown as $row)
        @php
            $yourAnswer = $row['your_answer'] ?? __('No answer recorded');
            $yourLetters = $extractLetters(is_string($yourAnswer) ? $yourAnswer : null);
            $correctLetters = $showCorrectSummaries && filled($row['correct_answer'] ?? null)
                ? $extractLetters((string) $row['correct_answer'])
                : [];
            $hasOptions = ! empty($row['options']) && is_array($row['options']);
            $outcome = $row['outcome'] ?? 'neutral';
            $questionWrong = $outcome === 'incorrect';
            $scoreClass = $questionWrong ? 'qs-result-q-item__score--wrong' : '';
        @endphp
        <li class="qs-result-q-item">
            <div class="qs-result-q-item__row">
                <span class="qs-result-q-item__num" aria-hidden="true">{{ $row['number'] }}</span>

                <div class="qs-result-q-item__main">
                    <div class="qs-result-q-item__question-row">
                        <p class="qs-result-q-item__prompt">{{ $row['question_text'] }}</p>
                        <p class="qs-result-q-item__score {{ $scoreClass }}" title="{{ __('Points earned') }}">
                            {{ $row['points'] }}<span>/{{ $row['max'] }}</span>
                        </p>
                    </div>

                    @if ($hasOptions)
                        <ul class="qs-result-q-options" aria-label="{{ __('Answer choices') }}">
                            @foreach ($row['options'] as $optIndex => $optionText)
                                @php
                                    $letter = chr(65 + (int) $optIndex);
                                    $isYours = in_array($letter, $yourLetters, true);
                                    $isCorrectKey = $showCorrectSummaries && in_array($letter, $correctLetters, true);
                                    $pickIsRight = $isYours && (
                                        $isCorrectKey
                                        || (! $showCorrectSummaries && $outcome === 'correct')
                                    );
                                    $pickIsWrong = $isYours && (
                                        ($showCorrectSummaries && $correctLetters !== [] && ! $isCorrectKey)
                                        || (! $showCorrectSummaries && in_array($outcome, ['incorrect', 'partial'], true))
                                    );
                                    $rowClass = $pickIsWrong ? 'is-wrong-pick' : ($pickIsRight ? 'is-right-pick' : '');
                                    $yourTagClass = $pickIsWrong
                                        ? 'qs-result-q-tag--wrong'
                                        : ($pickIsRight ? 'qs-result-q-tag--you-right' : 'qs-result-q-tag--you');
                                @endphp
                                <li @class([$rowClass !== '' ? $rowClass : null])>
                                    <span class="qs-result-q-options__text">
                                        <span class="qs-result-q-options__letter">{{ $letter }}.</span>
                                        {{ $optionText }}
                                    </span>
                                    <span class="qs-result-q-options__marks">
                                        @if ($isYours)
                                            <span class="qs-result-q-tag {{ $yourTagClass }}">{{ __('Your pick') }}</span>
                                        @endif
                                        @if ($isCorrectKey)
                                            <span class="qs-result-q-tag qs-result-q-tag--correct">{{ __('Correct') }}</span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="qs-result-q-summary">
                            <div class="qs-result-q-summary__line">
                                <span class="qs-result-q-summary__label">{{ __('Your answer') }}</span>
                                @if (($row['type'] ?? null) === 'essay' && is_string($yourAnswer) && \App\Support\EssayAnswerHtml::looksLikeHtml($yourAnswer))
                                    <div @class([
                                        'qs-result-q-summary__value qs-essay-rendered',
                                        'qs-result-q-summary__value--wrong' => in_array($outcome, ['incorrect', 'partial'], true),
                                        'qs-result-q-summary__value--correct' => $outcome === 'correct',
                                    ])>{!! \App\Support\EssayAnswerHtml::sanitize($yourAnswer) !!}</div>
                                @else
                                    <span @class([
                                        'qs-result-q-summary__value',
                                        'qs-result-q-summary__value--wrong' => in_array($outcome, ['incorrect', 'partial'], true),
                                        'qs-result-q-summary__value--correct' => $outcome === 'correct',
                                    ])>{{ $yourAnswer }}</span>
                                @endif
                            </div>
                            @if ($showCorrectSummaries && filled($row['correct_answer'] ?? null))
                                <div class="qs-result-q-summary__line">
                                    <span class="qs-result-q-summary__label">{{ __('Correct') }}</span>
                                    <span class="qs-result-q-summary__value qs-result-q-summary__value--correct">{{ $row['correct_answer'] }}</span>
                                </div>
                            @endif
                        </div>
                    @endif

                    @if (filled($row['feedback'] ?? null))
                        <p class="qs-result-q-feedback">
                            <span class="font-semibold text-[#667085]">{{ __('Feedback') }}:</span>
                            {{ $row['feedback'] }}
                        </p>
                    @endif
                </div>
            </div>
        </li>
    @endforeach
</ol>
