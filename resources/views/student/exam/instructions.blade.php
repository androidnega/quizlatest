@extends('layouts.exam-entry')

@section('title', __('Dos and don’ts').' — '.config('app.name', 'QuizSnap'))

@section('content')
    <div class="flex w-full max-w-lg flex-col items-center px-1 py-2 sm:max-w-xl">
        <div class="w-full rounded-2xl border border-qs-soft bg-qs-surface p-6 shadow-sm sm:p-8">
            @include('student.exam.partials.dos-and-donts-body', ['isAssignment' => $isAssignment ?? false])

            <div class="mt-8 flex justify-center border-t border-qs-soft pt-6">
                <a href="{{ route('dashboard') }}" class="qs-btn-secondary inline-flex items-center gap-2 text-sm">
                    <i class="fa-solid fa-arrow-left text-xs" aria-hidden="true"></i>
                    {{ __('Back to dashboard') }}
                </a>
            </div>
        </div>
    </div>
@endsection
