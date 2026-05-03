<x-layouts.coordinator>
    <x-slot name="title">{{ __('Course materials') }}</x-slot>
    <x-slot name="subtitle">{{ $course->code }} — {{ $course->title }}</x-slot>

    <div class="space-y-8">
        @if (session('status'))
            <div class="rounded-xl border border-qs-soft bg-qs-card px-4 py-3 text-sm text-qs-text">{{ session('status') }}</div>
        @endif

        <section class="rounded-xl border border-qs-soft bg-qs-bg p-6">
            <h3 class="text-sm font-semibold text-qs-text">{{ __('Upload material') }}</h3>
            @if ($errors->any())
                <ul class="mt-2 list-disc ps-5 text-sm text-qs-danger">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            @endif
            <form method="POST" action="{{ route('examiner.courses.materials.store', $course) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-qs-muted">{{ __('Title') }}</label>
                    <input type="text" name="title" value="{{ old('title') }}" required class="qs-input mt-2 w-full py-2.5" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-qs-muted">{{ __('Limit to class (optional)') }}</label>
                    <select name="class_id" class="qs-input mt-2 w-full py-2.5">
                        <option value="">{{ __('All classes with this course') }}</option>
                        @foreach ($classes as $cl)
                            <option value="{{ $cl->id }}" @selected((string) old('class_id') === (string) $cl->id)>{{ $cl->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-qs-muted">{{ __('File (PDF, DOCX, TXT)') }}</label>
                    <input type="file" name="file" required class="mt-2 block w-full text-sm text-qs-text" />
                </div>
                <button type="submit" class="qs-btn-primary min-h-[44px] px-4 text-sm font-semibold">{{ __('Upload') }}</button>
            </form>
        </section>

        <div class="qs-table-wrap rounded-xl border border-qs-soft">
            <table class="qs-table">
                <thead>
                    <tr>
                        <th class="text-left">{{ __('Title') }}</th>
                        <th class="text-left">{{ __('Type') }}</th>
                        <th class="text-left">{{ __('Status') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($materials as $m)
                        <tr>
                            <td class="text-sm text-qs-text">{{ $m->title }}</td>
                            <td class="text-sm text-qs-muted">{{ strtoupper($m->file_type) }}</td>
                            <td class="text-sm text-qs-muted">{{ $m->status }}</td>
                            <td class="text-right space-x-3">
                                <a href="{{ route('examiner.courses.materials.download', [$course, $m]) }}" class="text-sm font-medium text-qs-text underline-offset-2 hover:underline">{{ __('Download') }}</a>
                                <form method="POST" action="{{ route('examiner.courses.materials.destroy', [$course, $m]) }}" class="inline" onsubmit="return confirm(@json(__('Delete this material?')));">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm text-qs-danger">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-sm text-qs-muted">{{ __('No materials yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $materials->links() }}</div>
    </div>
</x-layouts.coordinator>
