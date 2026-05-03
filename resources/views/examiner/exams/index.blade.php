<x-layouts.coordinator>
    <x-slot name="title">Exams</x-slot>
    <x-slot name="subtitle">Build assessments for your assigned courses</x-slot>

    <div class="mb-6 flex justify-end">
        <a href="{{ route('examiner.exams.create') }}" class="qs-btn-primary inline-flex min-h-[44px] items-center justify-center px-4 text-sm font-semibold">
            {{ __('Create exam') }}
        </a>
    </div>

    <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm">
        <div class="qs-table-wrap -mx-1 border-0 bg-transparent sm:mx-0">
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
                    @forelse ($exams as $exam)
                        <tr>
                            <td class="font-medium">{{ $exam->title }}</td>
                            <td class="text-qs-muted">{{ $exam->course?->code }} — {{ $exam->course?->title }}</td>
                            <td>
                                <span class="inline-flex rounded-full border border-qs-soft bg-qs-card px-2 py-0.5 text-xs font-medium text-qs-text">{{ $exam->status }}</span>
                            </td>
                            <td>
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('coordinator.exams.sessions.index', $exam) }}" class="qs-btn-secondary inline-flex min-h-[44px] items-center justify-center px-3 py-2 text-xs font-semibold">
                                        {{ __('Sessions') }}
                                    </a>
                                    <a href="{{ route('examiner.exams.builder', $exam) }}" class="qs-btn-primary inline-flex min-h-[44px] items-center justify-center px-3 py-2 text-xs font-semibold">
                                        {{ __('Builder') }}
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-10 text-center text-sm text-qs-muted">{{ __('No exams yet. Create one to get started.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $exams->links() }}</div>
    </div>
</x-layouts.coordinator>
