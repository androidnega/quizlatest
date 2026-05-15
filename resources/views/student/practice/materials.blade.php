<x-layouts.student>
    <x-slot name="title">{{ __('Course materials') }}</x-slot>

    <div class="mx-auto max-w-3xl space-y-4 pb-6">
        <p class="text-sm leading-relaxed text-slate-600">
            {{ __('Files and outlines from courses you are enrolled in. Downloads are only available for materials your lecturer published for your class.') }}
        </p>

        @if ($materials->isEmpty())
            <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-12 text-center">
                <p class="text-sm font-medium text-slate-800">{{ __('Nothing here yet') }}</p>
                <p class="mx-auto mt-2 max-w-md text-xs leading-relaxed text-slate-600">
                    {{ __('When your lecturer publishes materials for your class, they will appear here as cards with a download button.') }}
                </p>
            </div>
        @else
            <ul class="space-y-3">
                @foreach ($materials as $m)
                    <li class="rounded-xl border border-slate-200 bg-white p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-slate-900">{{ $m->title }}</p>
                                <p class="mt-1 text-xs text-slate-600">
                                    {{ $m->course?->code }} — {{ $m->course?->title }}
                                </p>
                                <div class="mt-2 flex flex-wrap gap-2 text-[11px]">
                                    @if ($m->material_kind === \App\Models\CourseMaterial::KIND_COURSE_OUTLINE)
                                        <span class="rounded-md bg-sky-50 px-2 py-0.5 font-medium text-sky-900 ring-1 ring-sky-200/80">{{ __('Outline') }}</span>
                                    @else
                                        <span class="rounded-md bg-slate-100 px-2 py-0.5 font-medium text-slate-800 ring-1 ring-slate-200/80">{{ __('File') }}</span>
                                    @endif
                                    <span class="rounded-md bg-slate-50 px-2 py-0.5 font-mono font-medium uppercase text-slate-600 ring-1 ring-slate-200/80">{{ strtoupper((string) $m->file_type) }}</span>
                                    @if ($m->created_at)
                                        <span class="rounded-md bg-slate-50 px-2 py-0.5 text-slate-600 ring-1 ring-slate-200/80">
                                            {{ __('Uploaded') }} {{ $m->created_at->timezone(config('app.timezone'))->format('M j, Y') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <a
                                href="{{ route('student.practice.materials.download', $m) }}"
                                class="inline-flex min-h-[44px] shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-4 text-xs font-semibold text-slate-900 hover:bg-slate-100"
                            >
                                <i class="fa-solid fa-download me-2" aria-hidden="true"></i>
                                {{ __('Download') }}
                            </a>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.student>
