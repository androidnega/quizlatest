<x-layouts.examiner>
    <x-slot name="title">{{ __('Classes') }}</x-slot>
    <x-slot name="subtitle">{{ __('Classes linked to your assigned courses') }}</x-slot>

    <div class="space-y-4">
        @if ($classrooms->isEmpty())
            <div class="rounded-xl border border-dashed border-qs-soft bg-white px-4 py-8 text-center text-sm text-qs-muted shadow-sm">
                {{ __('No classes are linked to your course assignments yet. Coordinators attach courses to classes; once they do, classes appear here.') }}
            </div>
        @else
            <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($classrooms as $classroom)
                    @php($overlap = (int) ($courseCountByClass[$classroom->id] ?? 0))
                    <li>
                        <a
                            href="{{ route('examiner.teaching-classes.show', $classroom) }}"
                            class="group flex h-full flex-col rounded-xl border border-qs-soft bg-white p-4 shadow-sm hover:border-qs-accent/40 hover:shadow-md"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-base font-semibold text-qs-text group-hover:text-qs-primary">{{ $classroom->name }}</p>
                                    @if ($classroom->section)
                                        <p class="mt-0.5 text-xs text-qs-muted">{{ $classroom->section }}</p>
                                    @endif
                                    @if ($classroom->level)
                                        <p class="mt-1 text-xs font-medium text-qs-muted">{{ $classroom->level->code ?? $classroom->level->name }}</p>
                                    @endif
                                </div>
                                <span class="shrink-0 rounded-full border border-qs-soft bg-qs-bg px-2 py-0.5 text-[11px] font-semibold text-qs-text">{{ trans_choice(':count student|:count students', $classroom->students_count, ['count' => $classroom->students_count]) }}</span>
                            </div>
                            <p class="mt-3 text-xs text-qs-muted">
                                {{ trans_choice(':count course you teach here|:count courses you teach here', $overlap, ['count' => $overlap]) }}
                            </p>
                            <span class="mt-auto pt-3 text-xs font-semibold text-qs-primary">{{ __('Open') }} →</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.examiner>
