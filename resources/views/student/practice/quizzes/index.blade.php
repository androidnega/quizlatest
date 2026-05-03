<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl qs-heading leading-tight">{{ __('My practice quizzes') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('student.practice.quizzes.create') }}" class="qs-btn-primary text-sm">{{ __('Generate new') }}</a>
                <a href="{{ route('student.practice.index') }}" class="qs-btn-secondary text-sm">{{ __('Practice home') }}</a>
            </div>

            @if ($quizzes->isEmpty())
                <p class="text-sm text-qs-muted">{{ __('You have no practice quizzes yet.') }}</p>
            @else
                <div class="qs-table-wrap rounded-xl border border-qs-soft">
                    <table class="qs-table">
                        <thead>
                            <tr>
                                <th class="text-left">{{ __('Title') }}</th>
                                <th class="text-left">{{ __('Course') }}</th>
                                <th class="text-left">{{ __('Status') }}</th>
                                <th class="text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($quizzes as $q)
                                <tr>
                                    <td class="text-sm text-qs-text">{{ $q->title }}</td>
                                    <td class="text-sm text-qs-muted">{{ $q->course?->code }}</td>
                                    <td class="text-sm text-qs-muted">{{ $q->status }} · {{ $q->questions_count }} {{ __('questions') }}</td>
                                    <td class="text-right">
                                        @if ($q->status === \App\Models\PracticeQuiz::STATUS_READY)
                                            <a href="{{ route('student.practice.quizzes.show', $q) }}" class="text-sm font-medium text-qs-text underline-offset-2 hover:underline">{{ __('Open') }}</a>
                                        @else
                                            <span class="text-xs text-qs-muted">{{ __('Unavailable') }}</span>
                                        @endif
                                        <form method="POST" action="{{ route('student.practice.quizzes.destroy', $q) }}" class="ms-2 inline" onsubmit="return confirm(@json(__('Delete this practice quiz?')));">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm text-qs-danger">{{ __('Delete') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $quizzes->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
