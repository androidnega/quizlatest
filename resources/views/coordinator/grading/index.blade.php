<x-layouts.coordinator>
    <x-slot name="title">Essay grading</x-slot>
    <x-slot name="subtitle">Pending manual grades</x-slot>

    <div class="bg-white rounded-xl border border-qs-soft shadow-sm p-5 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-qs-soft/30">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-qs-muted">Student</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-qs-muted">Exam</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-qs-muted">Question</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-qs-muted">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($answers as $answer)
                    <tr class="border-t border-qs-soft">
                        <td class="px-3 py-2">{{ $answer->examSession?->student?->name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $answer->examSession?->exam?->title ?? '—' }}</td>
                        <td class="px-3 py-2 line-clamp-2">{{ \Illuminate\Support\Str::limit($answer->question?->question_text ?? '', 80) }}</td>
                        <td class="px-3 py-2 text-right">
                            <a href="{{ route('coordinator.grading.show', $answer) }}" class="qs-btn-primary px-3 py-1.5 text-xs">Grade</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-3 py-8 text-center text-qs-muted">No pending essay answers.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $answers->links() }}</div>
    </div>
</x-layouts.coordinator>
