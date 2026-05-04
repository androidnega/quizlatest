<x-layouts.examiner>
    <x-slot name="title">Essay grading</x-slot>
    <x-slot name="subtitle">Pending manual grades</x-slot>

    <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm">
        <div class="qs-table-wrap -mx-1 border-0 bg-transparent sm:mx-0">
            <table class="qs-table">
                <thead>
                    <tr>
                        <th class="text-left">{{ __('Student') }}</th>
                        <th class="text-left">{{ __('Exam') }}</th>
                        <th class="text-left">{{ __('Question') }}</th>
                        <th class="text-right">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($answers as $answer)
                        <tr>
                            <td class="font-medium">{{ $answer->examSession?->student?->name ?? '—' }}</td>
                            <td class="text-qs-muted">{{ $answer->examSession?->exam?->title ?? '—' }}</td>
                            <td class="max-w-xs text-qs-muted line-clamp-2">{{ \Illuminate\Support\Str::limit($answer->question?->question_text ?? '', 80) }}</td>
                            <td class="text-right">
                                <a href="{{ route('examiner.grading.show', $answer) }}" class="qs-btn-primary inline-flex min-h-[44px] items-center justify-center px-4 py-2 text-xs font-semibold">{{ __('Grade') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-10 text-center text-sm text-qs-muted">{{ __('No pending essay answers.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $answers->links() }}</div>
    </div>
</x-layouts.examiner>
