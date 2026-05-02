<x-layouts.coordinator>
    <x-slot name="title">Exams</x-slot>
    <x-slot name="subtitle">Build assessments for your assigned courses</x-slot>

    <div class="mb-6 flex justify-end">
        <a href="{{ route('examiner.exams.create') }}" class="rounded-lg bg-qs-accent px-4 py-2 text-sm font-semibold text-qs-text hover:opacity-95">
            Create exam
        </a>
    </div>

    <div class="bg-qs-bg rounded-xl border border-qs-soft shadow-sm p-5">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-qs-soft/30">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-qs-muted">Title</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-qs-muted">Course</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-qs-muted">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-qs-muted">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($exams as $exam)
                        <tr class="border-t border-qs-soft hover:bg-qs-card">
                            <td class="px-4 py-3 text-sm text-qs-text">{{ $exam->title }}</td>
                            <td class="px-4 py-3 text-sm text-qs-muted">{{ $exam->course?->code }} — {{ $exam->course?->title }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs bg-qs-card text-qs-text">{{ $exam->status }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-right space-x-2">
                                <a href="{{ route('coordinator.exams.sessions.index', $exam) }}" class="qs-btn-secondary inline-block px-3 py-1.5 text-xs font-semibold">
                                    Sessions
                                </a>
                                <a href="{{ route('examiner.exams.builder', $exam) }}" class="qs-btn-primary inline-block px-3 py-1.5 text-xs font-semibold">
                                    Builder
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-sm text-qs-muted">No exams yet. Create one to get started.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $exams->links() }}</div>
    </div>
</x-layouts.coordinator>
