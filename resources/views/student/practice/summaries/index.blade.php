<x-layouts.student>
    <x-slot name="title">{{ __('Study summaries') }}</x-slot>

    <div class="mx-auto max-w-4xl space-y-10 py-2">
            <section class="rounded-xl border border-qs-soft bg-qs-bg p-6">
                <h3 class="text-sm font-semibold text-qs-text">{{ __('Generate summary') }}</h3>
                @if ($errors->has('summary'))
                    <p class="mt-2 text-sm text-qs-danger">{{ $errors->first('summary') }}</p>
                @endif
                @if ($materialRows->isEmpty())
                    <p class="mt-2 text-sm text-qs-muted">{{ __('No materials available yet.') }}</p>
                @else
                    <form method="POST" action="{{ route('student.practice.summaries.store') }}" class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end">
                        @csrf
                        <div class="min-w-0 flex-1">
                            <label class="block text-xs font-medium text-qs-muted">{{ __('Material') }}</label>
                            <select name="course_material_id" required class="qs-input mt-2 w-full py-2.5">
                                @foreach ($materialRows as $m)
                                    <option value="{{ $m->id }}">{{ $m->course?->code ?? '' }} — {{ $m->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="qs-btn-primary min-h-[44px] shrink-0 px-4 text-sm font-semibold">{{ __('Generate') }}</button>
                    </form>
                @endif
            </section>

            <section>
                <h3 class="text-sm font-semibold text-qs-text">{{ __('Your summaries') }}</h3>
                @if ($summaries->isEmpty())
                    <p class="mt-2 text-sm text-qs-muted">{{ __('None yet.') }}</p>
                @else
                    <ul class="mt-4 space-y-3">
                        @foreach ($summaries as $s)
                            <li class="rounded-xl border border-qs-soft bg-qs-card px-4 py-3">
                                <a href="{{ route('student.practice.summaries.show', $s) }}" class="font-medium text-qs-text underline-offset-2 hover:underline">{{ $s->title }}</a>
                                <p class="text-xs text-qs-muted">{{ $s->course?->code }} · {{ $s->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</p>
                            </li>
                        @endforeach
                    </ul>
                    <div class="mt-4">{{ $summaries->links() }}</div>
                @endif
            </section>
    </div>
</x-layouts.student>
