<x-layouts.examiner>
    <x-slot name="title">{{ __('Course outline') }}</x-slot>
    <x-slot name="subtitle">{{ $course->code }} — {{ $course->title }}</x-slot>

    <div class="mx-auto max-w-3xl space-y-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('examiner.courses.show', $course) }}" class="text-sm font-medium text-sky-700 underline-offset-2 hover:underline">
                ← {{ __('Back to course') }}
            </a>
            <a
                href="{{ route('examiner.courses.materials.index', $course) }}"
                class="text-sm font-medium text-slate-600 underline-offset-2 hover:text-slate-900 hover:underline"
            >
                {{ __('All course files') }} →
            </a>
        </div>

        @if (session('status'))
            <div class="flex items-start gap-3 rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-950 shadow-sm">
                <i class="fa-solid fa-circle-check mt-0.5 text-emerald-600" aria-hidden="true"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <header class="rounded-2xl border border-slate-200/90 bg-gradient-to-br from-white via-slate-50/30 to-sky-50/40 p-6 shadow-sm ring-1 ring-black/[0.04] sm:p-8">
            <p class="text-[11px] font-bold uppercase tracking-widest text-slate-500">{{ __('Upload') }}</p>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">{{ __('Course outline') }}</h1>
            <p class="mt-2 max-w-xl text-sm leading-relaxed text-slate-600">
                {{ __('Share the official syllabus or outline as PDF, Word, or plain text. Students in linked class groups can download it from their materials library once processing completes.') }}
            </p>
        </header>

        <section class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm sm:p-8">
            <h2 class="text-sm font-semibold text-slate-900">{{ __('Upload a new outline') }}</h2>
            @if ($errors->any())
                <ul class="mt-3 list-disc space-y-1 ps-5 text-sm text-red-700">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            @endif
            <form
                method="POST"
                action="{{ route('examiner.courses.materials.store', $course) }}"
                enctype="multipart/form-data"
                class="mt-6 space-y-5"
            >
                @csrf
                <input type="hidden" name="material_kind" value="{{ \App\Models\CourseMaterial::KIND_COURSE_OUTLINE }}" />

                <div>
                    <label for="outline-title" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Title') }}</label>
                    <input
                        id="outline-title"
                        type="text"
                        name="title"
                        value="{{ old('title', __('Course outline')) }}"
                        required
                        class="mt-2 block w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3 text-sm font-medium text-slate-900 shadow-inner ring-slate-200 transition focus:border-sky-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                    />
                </div>
                <div>
                    <label for="outline-class" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Visible to class (optional)') }}</label>
                    <select
                        id="outline-class"
                        name="class_id"
                        class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-900 shadow-sm focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                    >
                        <option value="">{{ __('All classes taking this course') }}</option>
                        @foreach ($classes as $cl)
                            <option value="{{ $cl->id }}" @selected((string) old('class_id') === (string) $cl->id)>{{ $cl->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-xs text-slate-500">{{ __('Leave blank to share the same file with every linked class.') }}</p>
                </div>
                <div>
                    <label for="outline-file" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('File') }}</label>
                    <div
                        class="mt-2 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50 px-4 py-10 text-center transition hover:border-sky-300 hover:bg-sky-50/30"
                    >
                        <i class="fa-solid fa-cloud-arrow-up text-2xl text-sky-600" aria-hidden="true"></i>
                        <p class="mt-2 text-sm font-semibold text-slate-800">{{ __('Drop a file here or browse') }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ __('PDF, DOCX, or TXT · max 12 MB') }}</p>
                        <input id="outline-file" type="file" name="file" required class="mt-4 block w-full max-w-xs text-sm text-slate-600 file:me-3 file:rounded-lg file:border-0 file:bg-sky-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-sky-700" />
                    </div>
                </div>
                <div class="flex flex-wrap gap-3 pt-2">
                    <button type="submit" class="inline-flex min-h-[44px] items-center gap-2 rounded-xl bg-sky-600 px-6 text-sm font-bold text-white shadow-md shadow-sky-600/25 transition hover:bg-sky-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2">
                        <i class="fa-solid fa-upload text-xs" aria-hidden="true"></i>
                        {{ __('Upload outline') }}
                    </button>
                </div>
            </form>
        </section>

        @if ($latestOutlines->isNotEmpty())
            <section class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm sm:p-8">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Recent outlines') }}</h2>
                <ul class="mt-4 divide-y divide-slate-100">
                    @foreach ($latestOutlines as $m)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div>
                                <p class="font-medium text-slate-900">{{ $m->title }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ strtoupper($m->file_type) }}
                                    ·
                                    {{ $m->status }}
                                    @if ($m->classroom)
                                        · {{ $m->classroom->name }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-3 text-xs font-semibold">
                                <a href="{{ route('examiner.courses.materials.download', [$course, $m]) }}" class="text-sky-600 hover:underline">{{ __('Download') }}</a>
                                <form method="POST" action="{{ route('examiner.courses.materials.destroy', [$course, $m]) }}" class="inline" onsubmit="return confirm(@json(__('Delete this outline?')));">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts.examiner>
