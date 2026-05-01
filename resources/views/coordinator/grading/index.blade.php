<x-layouts.coordinator>
    <x-slot name="title">Essay grading</x-slot>
    <x-slot name="subtitle">Pending manual grades</x-slot>

    <div class="bg-white rounded-xl border border-beige shadow-sm p-5 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-beige/60">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-600">Student</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-600">Exam</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-600">Question</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-600">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($answers as $answer)
                    <tr class="border-t border-beige">
                        <td class="px-3 py-2">{{ $answer->examSession?->student?->name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $answer->examSession?->exam?->title ?? '—' }}</td>
                        <td class="px-3 py-2 line-clamp-2">{{ \Illuminate\Support\Str::limit($answer->question?->question_text ?? '', 80) }}</td>
                        <td class="px-3 py-2 text-right">
                            <a href="{{ route('coordinator.grading.show', $answer) }}" class="rounded-lg bg-sage px-3 py-1.5 text-xs font-semibold text-white hover:opacity-90">Grade</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-3 py-8 text-center text-gray-600">No pending essay answers.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $answers->links() }}</div>
    </div>
</x-layouts.coordinator>
