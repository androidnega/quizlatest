<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Exam result') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f4f4f4; font-size: 10px; text-transform: uppercase; }
        .muted { color: #555; margin: 4px 0; }
        .block { margin-bottom: 14px; }
    </style>
</head>
<body>
    <h1>{{ $session->exam?->title ?? __('Exam') }}</h1>
    <div class="block">
        <div><strong>{{ __('Student') }}:</strong> {{ $session->student?->name }}</div>
        <div class="muted"><strong>{{ __('Index') }}:</strong> {{ $session->student?->index_number ?? '—' }}</div>
    </div>
    <div class="block">
        <div><strong>{{ __('Score') }}:</strong> {{ $result->score }} / {{ $session->exam?->total_marks ?? '—' }}</div>
        <div class="muted"><strong>{{ __('Percentage') }}:</strong> {{ $percentage !== null ? $percentage.'%' : '—' }}</div>
    </div>
    @if ($examinerFeedback)
        <div class="block">
            <strong>{{ __('Examiner feedback') }}</strong>
            <div class="muted" style="white-space: pre-wrap;">{{ $examinerFeedback }}</div>
        </div>
    @endif
    @if (count($breakdown) > 0)
        <strong>{{ __('Question breakdown') }}</strong>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Points') }}</th>
                    <th>{{ __('Max') }}</th>
                    @if ($showCorrectSummaries)
                        <th>{{ __('Correct') }}</th>
                    @endif
                    <th>{{ __('Feedback') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($breakdown as $row)
                    <tr>
                        <td>{{ $row['number'] }}</td>
                        <td>{{ str_replace('_', ' ', $row['type']) }}</td>
                        <td>{{ $row['points'] }}</td>
                        <td>{{ $row['max'] }}</td>
                        @if ($showCorrectSummaries)
                            <td>{{ $row['correct_summary'] ?? '—' }}</td>
                        @endif
                        <td>{{ $row['feedback'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
