<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $documentTitle ?? __('Assessment result') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; }
        h1 { font-size: 16px; margin: 0 0 12px; }
        h2 { font-size: 13px; margin: 16px 0 8px; }
        .muted { color: #64748b; margin: 4px 0; }
        .block { margin-bottom: 14px; }
        .question { border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; margin-bottom: 10px; page-break-inside: avoid; }
        .question-title { font-weight: bold; margin: 0 0 6px; }
        .question-text { margin: 0 0 8px; line-height: 1.4; }
        .label { font-size: 9px; text-transform: uppercase; color: #64748b; font-weight: bold; margin: 6px 0 2px; }
        .answer { white-space: pre-wrap; margin: 0; }
        .score { float: right; font-weight: bold; }
        .options { margin: 0 0 8px; padding-left: 16px; }
    </style>
</head>
<body>
    <h1>{{ $documentTitle ?? ($session->exam?->title ?? __('Assessment result')) }}</h1>
    <div class="block">
        <div><strong>{{ __('Student') }}:</strong> {{ $session->student?->name }}</div>
        <div class="muted"><strong>{{ __('Index') }}:</strong> {{ $session->student?->index_number ?? '—' }}</div>
    </div>
    <div class="block">
        @php
            $fmtMark = static fn ($v) => is_numeric($v)
                ? rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.')
                : ($v ?? '—');
        @endphp
        <div><strong>{{ __('Score') }}:</strong> {{ $fmtMark($result->score) }} / {{ $fmtMark($session->exam?->total_marks) }}</div>
        <div class="muted"><strong>{{ __('Percentage') }}:</strong> {{ $percentage !== null ? $percentage.'%' : '—' }}</div>
    </div>
    @if ($examinerFeedback)
        <div class="block">
            <strong>{{ __('Examiner feedback') }}</strong>
            <div class="muted" style="white-space: pre-wrap;">{{ $examinerFeedback }}</div>
        </div>
    @endif
    @if (count($breakdown) > 0)
        <h2>{{ __('Question breakdown') }}</h2>
        @foreach ($breakdown as $row)
            <div class="question">
                <p class="question-title">
                    {{ __('Question :n', ['n' => $row['number']]) }} — {{ $row['type_label'] }}
                    <span class="score">{{ $row['points'] }} / {{ $row['max'] }}</span>
                </p>
                <p class="question-text">{{ $row['question_text'] }}</p>
                @if (! empty($row['options']) && is_array($row['options']))
                    <ul class="options">
                        @foreach ($row['options'] as $optIndex => $optionText)
                            <li>{{ chr(65 + (int) $optIndex) }}. {{ $optionText }}</li>
                        @endforeach
                    </ul>
                @endif
                <p class="label">{{ __('Your answer') }}</p>
                <p class="answer">{{ $row['your_answer'] ?? __('No answer recorded') }}</p>
                @if ($showCorrectSummaries && filled($row['correct_answer'] ?? null))
                    <p class="label">{{ __('Correct answer') }}</p>
                    <p class="answer">{{ $row['correct_answer'] }}</p>
                @endif
                @if (filled($row['feedback'] ?? null))
                    <p class="label">{{ __('Feedback') }}</p>
                    <p class="answer">{{ $row['feedback'] }}</p>
                @endif
            </div>
        @endforeach
    @endif
</body>
</html>
