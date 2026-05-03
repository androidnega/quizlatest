<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl qs-heading leading-tight">{{ __('Course materials') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if ($materials->isEmpty())
                <p class="text-sm text-qs-muted">{{ __('No materials are available for your enrolled courses yet.') }}</p>
            @else
                <ul class="space-y-3">
                    @foreach ($materials as $m)
                        <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-qs-soft bg-qs-bg px-4 py-4">
                            <div>
                                <p class="font-medium text-qs-text">{{ $m->title }}</p>
                                <p class="text-xs text-qs-muted">{{ $m->course?->code }} · {{ strtoupper($m->file_type) }}</p>
                            </div>
                            <a href="{{ route('student.practice.materials.download', $m) }}" class="qs-btn-secondary text-sm">{{ __('Download') }}</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-app-layout>
