<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl qs-heading leading-tight">{{ __('Practice attempt') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-3xl space-y-8 px-4 sm:px-6 lg:px-8">
            <p class="text-sm text-qs-muted">{{ __('Unofficial practice — not proctored or graded officially.') }}</p>

            <form method="POST" action="{{ route('student.practice.quizzes.submit', $quiz) }}" class="space-y-8">
                @csrf
                @foreach ($quiz->questions as $q)
                    <div class="rounded-xl border border-qs-soft bg-qs-bg p-5">
                        <p class="text-sm font-medium text-qs-text">{{ $loop->iteration }}. {{ $q->question_text }}</p>
                        @if ($q->type === 'mcq' && is_array($q->options))
                            <div class="mt-3 space-y-2">
                                @foreach ($q->options as $i => $opt)
                                    <label class="flex cursor-pointer items-start gap-2 text-sm text-qs-text">
                                        <input type="radio" name="answers[{{ $q->id }}]" value="{{ $i }}" class="mt-1 size-4 border-qs-soft text-qs-accent" />
                                        <span>{{ $opt }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @elseif ($q->type === 'true_false')
                            <div class="mt-3 flex flex-wrap gap-4 text-sm">
                                <label class="flex cursor-pointer items-center gap-2"><input type="radio" name="answers[{{ $q->id }}]" value="1" class="size-4 border-qs-soft text-qs-accent" /> {{ __('True') }}</label>
                                <label class="flex cursor-pointer items-center gap-2"><input type="radio" name="answers[{{ $q->id }}]" value="0" class="size-4 border-qs-soft text-qs-accent" /> {{ __('False') }}</label>
                            </div>
                        @elseif ($q->type === 'fill_blank')
                            <input type="text" name="answers[{{ $q->id }}]" class="qs-input mt-3 w-full py-2.5" autocomplete="off" />
                        @else
                            <textarea name="answers[{{ $q->id }}]" rows="4" class="qs-input mt-3 w-full py-2.5 text-sm"></textarea>
                        @endif
                    </div>
                @endforeach

                <button type="submit" class="qs-btn-primary min-h-[44px] px-4 text-sm font-semibold">{{ __('Submit answers') }}</button>
            </form>
        </div>
    </div>
</x-app-layout>
