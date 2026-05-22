@extends('layouts.exam-runtime', [
    'documentTitle' => $isAssignment ? __('Submitted') : __('Submitted'),
    'isAssignmentMode' => $isAssignment,
])

@section('content')
<style>
.qs-submit-success {
    display: flex;
    min-height: 100vh;
    align-items: center;
    justify-content: center;
    padding: 2.5rem 1.25rem;
    background: #ffffff;
    font-family: Inter, ui-sans-serif, system-ui, sans-serif;
    color: #0f172a;
}

.qs-submit-success__inner {
    width: 100%;
    max-width: 34rem;
    text-align: center;
}

.qs-submit-success__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 3.5rem;
    height: 3.5rem;
    margin-bottom: 1.25rem;
    color: #059669;
    font-size: 2.6rem;
    line-height: 1;
}

.qs-submit-success__title {
    margin: 0 0 0.65rem;
    font-size: 1.65rem;
    font-weight: 800;
    letter-spacing: -0.025em;
    line-height: 1.15;
    color: #0f172a;
}

.qs-submit-success__lead {
    margin: 0 auto 1.5rem;
    max-width: 28rem;
    font-size: 0.95rem;
    line-height: 1.6;
    color: #475569;
}

.qs-submit-success__meta {
    margin: 0 0 1rem;
    font-size: 0.875rem;
    line-height: 1.4;
    color: #475569;
}

.qs-submit-success__meta-title {
    font-weight: 700;
    color: #0f172a;
}

.qs-submit-success__meta-sep {
    margin: 0 0.25rem;
    color: #cbd5e1;
}

.qs-submit-success__meta-course {
    color: #64748b;
}

.qs-submit-success__notice {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin: 0 0 1.5rem;
    padding: 0;
    background: transparent;
    border: 0;
    font-size: 0.85rem;
    font-weight: 600;
    color: #b45309;
}

.qs-submit-success__notice i {
    color: #d97706;
}

.qs-submit-success__actions {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.65rem;
    margin-top: 0.5rem;
}

@media (min-width: 480px) {
    .qs-submit-success__actions {
        flex-direction: row;
        justify-content: center;
        gap: 0.85rem;
    }
}

.qs-submit-success__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    min-height: 2.85rem;
    padding: 0.6rem 1.4rem;
    border-radius: 0.65rem;
    font-size: 0.9rem;
    font-weight: 700;
    text-decoration: none;
    letter-spacing: -0.005em;
    transition: background-color 0.18s ease, color 0.18s ease, transform 0.18s ease;
}

.qs-submit-success__btn--primary {
    background: #0f172a;
    color: #ffffff;
}

.qs-submit-success__btn--primary i {
    font-size: 0.78rem;
    transition: transform 0.2s ease;
}

.qs-submit-success__btn--primary:hover {
    background: #1e293b;
}

.qs-submit-success__btn--primary:hover i {
    transform: translateX(3px);
}

.qs-submit-success__btn--ghost {
    background: transparent;
    color: #334155;
}

.qs-submit-success__btn--ghost:hover {
    color: #0f172a;
    text-decoration: underline;
    text-underline-offset: 4px;
    text-decoration-thickness: 1.5px;
}

@media (prefers-reduced-motion: reduce) {
    .qs-submit-success__btn,
    .qs-submit-success__btn--primary i {
        transition: none;
    }
}
</style>

<main class="qs-submit-success" role="main">
    <div class="qs-submit-success__inner">
        <span class="qs-submit-success__icon" aria-hidden="true">
            <i class="fa-solid fa-circle-check"></i>
        </span>

        <h1 class="qs-submit-success__title">
            @if ($isAssignment)
                {{ __('Assignment submitted') }}
            @else
                {{ __('Assessment submitted') }}
            @endif
        </h1>

        <p class="qs-submit-success__lead">
            @if ($isAssignment)
                {{ __('Your work was saved successfully. You can return to your assignments or view your submission when your instructor releases feedback.') }}
            @else
                {{ __('Your answers were saved successfully. You may close this page or view your result when it is released.') }}
            @endif
        </p>

        @if ($quiz?->title)
            <p class="qs-submit-success__meta">
                <span class="qs-submit-success__meta-title">{{ $quiz->title }}</span>
                @if ($quiz->course)
                    <span class="qs-submit-success__meta-sep">·</span>
                    <span class="qs-submit-success__meta-course">{{ $quiz->course->code }}</span>
                @endif
            </p>
        @endif

        @if ($examSession->submitted_late)
            <p class="qs-submit-success__notice">
                <i class="fa-solid fa-clock" aria-hidden="true"></i>
                {{ __('Submitted after the due date — marked as late.') }}
            </p>
        @endif

        <div class="qs-submit-success__actions">
            @if ($isAssignment)
                <a href="{{ route('student.assignments.index') }}" class="qs-submit-success__btn qs-submit-success__btn--primary">
                    {{ __('Back to assignments') }}
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            @else
                <a href="{{ route('dashboard') }}" class="qs-submit-success__btn qs-submit-success__btn--primary">
                    {{ __('Back to dashboard') }}
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            @endif
            <a href="{{ route('student.results.show', $examSession) }}" class="qs-submit-success__btn qs-submit-success__btn--ghost">
                {{ $isAssignment ? __('View submission') : __('View result') }}
            </a>
        </div>
    </div>
</main>
@endsection
