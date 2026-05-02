<x-layouts.coordinator>
    <x-slot name="title">Exams</x-slot>
    <x-slot name="subtitle">Build assessments for your assigned courses</x-slot>

    <div class="mb-6 flex justify-end">
        <a href="{{ route('examiner.exams.create') }}" class="rounded-lg bg-qs-accent px-4 py-2 text-sm font-semibold text-white hover:opacity-95">
            Create exam
        </a>
    </div>

    <div class="bg-white rounded-xl border border-qs-soft shadow-sm p-5">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-qs-soft/30">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Title</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Course</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($exams as $exam)
                        <tr class="border-t border-qs-soft hover:bg-qs-card">
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $exam->title }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $exam->course?->code }} — {{ $exam->course?->title }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs bg-gray-100 text-gray-800">{{ $exam->status }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-right">
                                <a href="{{ route('examiner.exams.builder', $exam) }}" class="rounded-lg bg-sage px-3 py-1.5 text-xs font-semibold text-white hover:opacity-90">
                                    Builder
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-600">No exams yet. Create one to get started.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $exams->links() }}</div>
    </div>
</x-layouts.coordinator>
