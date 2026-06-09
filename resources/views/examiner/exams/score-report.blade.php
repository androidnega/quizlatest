<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{{ __('Score report') }} – {{ $examName }}</title>
<style>
    @page { margin: 28mm 18mm 22mm 18mm; }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        color: #1f2937;
        font-size: 11px;
        line-height: 1.4;
        margin: 0;
    }
    .university {
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0 4px;
    }
    .report-title {
        color: #1d4ed8;
        font-size: 14px;
        font-weight: 700;
        margin: 0 0 14px;
        border-bottom: 2px solid #1d4ed8;
        padding-bottom: 6px;
    }
    .meta {
        margin: 0 0 18px;
        padding: 0;
        list-style: none;
        font-size: 11px;
        color: #1f2937;
    }
    .meta li {
        margin: 2px 0;
    }
    .meta strong {
        font-weight: 700;
        color: #0f172a;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 10.5px;
        margin-top: 4px;
    }
    thead th {
        background: #1d4ed8;
        color: #ffffff;
        text-align: left;
        font-weight: 700;
        padding: 7px 8px;
        border: 1px solid #1d4ed8;
        font-size: 10.5px;
    }
    tbody td {
        border: 1px solid #cbd5e1;
        padding: 6px 8px;
        vertical-align: middle;
    }
    tbody tr:nth-child(even) td {
        background: #f8fafc;
    }
    .col-no {
        width: 32px;
        text-align: center;
        color: #475569;
    }
    .col-mark {
        width: 90px;
        text-align: center;
        font-weight: 600;
    }
    .col-violation {
        width: 130px;
    }
    .held {
        color: #475569;
        font-style: italic;
        font-weight: 500;
        font-size: 10px;
    }
    .violation-flag {
        color: #b91c1c;
        font-weight: 600;
        background: #fee2e2;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 10px;
        display: inline-block;
    }
    .neutral {
        color: #94a3b8;
        text-align: center;
    }
    .footer {
        position: fixed;
        bottom: -10mm;
        left: 0;
        right: 0;
        font-size: 9px;
        color: #94a3b8;
        text-align: center;
    }
    .empty {
        text-align: center;
        padding: 40px 12px;
        color: #6b7280;
        font-style: italic;
    }
</style>
</head>
<body>

<h1 class="university">{{ $universityName }}</h1>
<h2 class="report-title">{{ __('Score report — :title', ['title' => $reportTitle]) }}</h2>

<ul class="meta">
    <li><strong>{{ __('Class group:') }}</strong> {{ $classGroupLine }}</li>
    <li><strong>{{ __('Lecturer:') }}</strong> {{ $lecturerName }}</li>
    <li><strong>{{ __('Course:') }}</strong> {{ $courseLine }}</li>
    <li><strong>{{ __('Exam:') }}</strong> {{ $examName }}</li>
    <li><strong>{{ __('Date:') }}</strong> {{ $generatedAt->format('F j, Y') }}</li>
    <li><strong>{{ __('Number of students:') }}</strong> {{ number_format($studentCount) }}</li>
</ul>

<table>
    <thead>
        <tr>
            <th class="col-no">{{ __('No.') }}</th>
            <th>{{ __('Student Index') }}</th>
            <th class="col-mark">{{ __('Mark') }}</th>
            <th class="col-violation">{{ __('Violation') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $i => $row)
            <tr>
                <td class="col-no">{{ $i + 1 }}</td>
                <td>{{ $row['index_number'] }}</td>
                <td class="col-mark">
                    @if ($row['is_held'])
                        <span class="held">{{ __('On hold – see lecturer') }}</span>
                    @else
                        {{ $row['mark'] }}/{{ $totalMarks }}
                    @endif
                </td>
                <td class="col-violation">
                    @if ($row['violation'] === '—')
                        <span class="neutral">—</span>
                    @else
                        <span class="violation-flag">{{ $row['violation'] }}</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="empty">{{ __('No submitted sessions yet.') }}</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    {{ __('Generated :date by :app', ['date' => $generatedAt->format('Y-m-d H:i'), 'app' => config('app.name')]) }}
</div>

</body>
</html>
