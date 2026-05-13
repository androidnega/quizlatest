<x-layouts.student>
    <x-slot name="title">{{ __('Course materials') }}</x-slot>

    <div class="mx-auto max-w-4xl space-y-6 py-2">
        <p class="text-sm leading-relaxed text-qs-muted">
            {{ __('Download outlines and files your lecturers uploaded for the courses you are enrolled in.') }}
        </p>

        @if ($materials->isEmpty())
            <div class="rounded-2xl border border-dashed border-qs-soft bg-qs-bg px-6 py-12 text-center">
                <p class="text-sm font-medium text-qs-text">{{ __('Nothing here yet') }}</p>
                <p class="mx-auto mt-2 max-w-md text-xs leading-relaxed text-qs-muted">
                    {{ __('When your lecturer publishes a course outline or materials for your class, they will show up in this list.') }}
                </p>
            </div>
        @else
            <div class="overflow-hidden rounded-2xl border border-qs-soft bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-qs-soft text-left text-sm">
                        <thead>
                            <tr class="bg-qs-bg text-[10px] font-bold uppercase tracking-wider text-qs-muted">
                                <th class="px-4 py-3 sm:px-5">{{ __('Title') }}</th>
                                <th class="px-4 py-3 sm:px-5">{{ __('Course') }}</th>
                                <th class="px-4 py-3 sm:px-5">{{ __('Kind') }}</th>
                                <th class="px-4 py-3 text-right sm:px-5">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-qs-soft">
                            @foreach ($materials as $m)
                                <tr class="bg-white transition hover:bg-qs-bg/40">
                                    <td class="px-4 py-4 align-middle font-medium text-qs-text sm:px-5">
                                        {{ $m->title }}
                                    </td>
                                    <td class="px-4 py-4 align-middle text-xs text-qs-muted sm:px-5">
                                        {{ $m->course?->code }} — <span class="text-qs-text">{{ $m->course?->title }}</span>
                                    </td>
                                    <td class="px-4 py-4 align-middle sm:px-5">
                                        @if ($m->material_kind === \App\Models\CourseMaterial::KIND_COURSE_OUTLINE)
                                            <span class="inline-flex rounded-full bg-sky-100 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-900 ring-1 ring-sky-200/80">
                                                {{ __('Outline') }}
                                            </span>
                                        @else
                                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-700 ring-1 ring-slate-200/80">
                                                {{ __('File') }}
                                            </span>
                                        @endif
                                        <span class="ms-2 text-[10px] font-mono text-qs-muted">{{ strtoupper($m->file_type) }}</span>
                                    </td>
                                    <td class="px-4 py-4 text-right align-middle sm:px-5">
                                        <a
                                            href="{{ route('student.practice.materials.download', $m) }}"
                                            class="qs-btn-secondary inline-flex items-center gap-2 text-sm"
                                        >
                                            <i class="fa-solid fa-download" aria-hidden="true"></i>
                                            {{ __('Download') }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-layouts.student>
