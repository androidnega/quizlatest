<x-layouts.coordinator>
    <x-slot name="title">{{ __('Edit student') }}</x-slot>
    <x-slot name="subtitle">{{ __('Update roster details for :name.', ['name' => $student->name]) }}</x-slot>

    <nav class="mb-5 min-w-0" aria-label="{{ __('Student editor actions') }}">
        <div class="flex min-w-0 flex-col divide-y divide-slate-200/80 rounded-2xl border border-slate-200/90 bg-slate-50/90 sm:flex-row sm:items-stretch sm:divide-x sm:divide-y-0">
            <a
                href="{{ route('coordinator.students.index') }}"
                class="flex min-h-[44px] items-center justify-center gap-2 px-4 py-3 text-sm font-medium text-slate-600 transition-colors hover:bg-white hover:text-slate-900 sm:flex-1 sm:justify-start"
            >
                <i class="fa-solid fa-chevron-left text-[10px] text-slate-400" aria-hidden="true"></i>
                <span>{{ __('Back to directory') }}</span>
            </a>
            @if ($student->classroom)
                <a
                    href="{{ route('coordinator.classes.show', $student->classroom) }}"
                    class="flex min-h-[44px] items-center justify-center gap-2 px-4 py-3 text-sm font-medium text-slate-600 transition-colors hover:bg-white hover:text-slate-900 sm:flex-1 sm:justify-start"
                >
                    <i class="fa-solid fa-users text-[12px] text-slate-400" aria-hidden="true"></i>
                    <span class="truncate">{{ __('Class: :name', ['name' => $student->classroom->name]) }}</span>
                </a>
            @endif
        </div>
    </nav>

    @if (session('status'))
        <div class="mb-5 rounded-xl border border-emerald-200/80 bg-emerald-50/80 px-4 py-3 text-sm text-emerald-900">
            {{ session('status') }}
        </div>
    @endif
    @if (session('generated_password'))
        <div class="mb-5 rounded-xl border border-amber-200/80 bg-amber-50/80 px-4 py-3 text-sm text-amber-900">
            <p class="font-semibold">{{ __('New generated password') }}: <span class="font-mono tracking-wide">{{ session('generated_password') }}</span></p>
            <p class="mt-1 text-xs text-amber-800">{{ __('Copy it now. For safety, this value is shown only once.') }}</p>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-rose-200/80 bg-rose-50/80 px-4 py-3 text-sm text-rose-900">
            {{ $errors->first() }}
        </div>
    @endif

    @php
        $displayProgram = $student->program?->name && $student->program?->code ? $student->program->name.' ('.$student->program->code.')' : ($student->program?->name ?? '—');
        $displayLevel = $student->level?->name && $student->level?->code ? $student->level->name.' ('.$student->level->code.')' : ($student->level?->name ?? '—');
        $displayClass = $student->classroom?->name && $student->classroom?->program?->code && $student->classroom?->level?->code
            ? $student->classroom->name.' — '.$student->classroom->program->code.' / '.$student->classroom->level->code
            : ($student->classroom?->name ?? __('Unassigned'));
    @endphp

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Identity') }}</p>
                <dl class="mt-3 space-y-2 text-sm">
                    <div><dt class="text-slate-500">{{ __('Full name') }}</dt><dd class="font-medium text-slate-900">{{ $student->name ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">{{ __('Index number') }}</dt><dd class="font-medium text-slate-900">{{ $student->index_number ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">{{ __('Phone') }}</dt><dd class="font-medium text-slate-900">{{ $student->phone ?: '—' }}</dd></div>
                </dl>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Enrollment') }}</p>
                <dl class="mt-3 space-y-2 text-sm">
                    <div><dt class="text-slate-500">{{ __('Program') }}</dt><dd class="font-medium text-slate-900">{{ $displayProgram }}</dd></div>
                    <div><dt class="text-slate-500">{{ __('Level') }}</dt><dd class="font-medium text-slate-900">{{ $displayLevel }}</dd></div>
                    <div><dt class="text-slate-500">{{ __('Class') }}</dt><dd class="font-medium text-slate-900">{{ $displayClass }}</dd></div>
                </dl>
            </div>
        </div>
        <div class="mt-5 rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3 text-sm">
            <span class="font-semibold text-slate-800">{{ __('Account status') }}:</span>
            <span class="{{ $student->is_active ? 'text-emerald-700' : 'text-slate-600' }}">{{ $student->is_active ? __('Active') : __('Inactive') }}</span>
        </div>
    </div>

    <details class="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm" @if ($errors->any()) open @endif>
        <summary class="flex cursor-pointer items-center justify-between px-5 py-4 text-sm font-semibold text-slate-800">
            <span>{{ __('Edit student') }}</span>
            <i class="fa-solid fa-pen text-xs text-slate-400" aria-hidden="true"></i>
        </summary>
        <div class="border-t border-slate-100 p-6">
            <form method="POST" action="{{ route('coordinator.students.update', $student) }}" class="space-y-8">
                @csrf
                @method('PUT')

                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Identity') }}</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="student_name" class="block text-sm font-medium text-slate-800">{{ __('Full name') }}</label>
                            <input id="student_name" type="text" name="name" value="{{ old('name', $student->name) }}" required autocomplete="name" class="qs-input mt-1 w-full py-2.5" />
                            @error('name')<p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="student_index" class="block text-sm font-medium text-slate-800">{{ __('Index number') }}</label>
                            <input id="student_index" type="text" name="index_number" value="{{ old('index_number', $student->index_number) }}" required class="qs-input mt-1 w-full py-2.5 tabular-nums" />
                            @error('index_number')<p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="student_phone" class="block text-sm font-medium text-slate-800">{{ __('Phone') }}</label>
                            <input id="student_phone" type="text" name="phone" value="{{ old('phone', $student->phone) }}" class="qs-input mt-1 w-full py-2.5" placeholder="{{ __('Ghana mobile, optional') }}" />
                            @error('phone')<p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Enrollment') }}</h2>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Program and level must match the teaching group when assigning a class.') }}</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="student_program_id" class="block text-sm font-medium text-slate-800">{{ __('Program') }}</label>
                            <select id="student_program_id" name="program_id" class="qs-input mt-1 w-full py-2.5" required>
                                @foreach ($programs as $program)
                                    <option value="{{ $program->id }}" @selected((int) old('program_id', $student->program_id) === $program->id)>{{ $program->name }} ({{ $program->code }})</option>
                                @endforeach
                            </select>
                            @error('program_id')<p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="student_level_id" class="block text-sm font-medium text-slate-800">{{ __('Level') }}</label>
                            <select id="student_level_id" name="level_id" class="qs-input mt-1 w-full py-2.5" required>
                                @foreach ($levels as $level)
                                    <option value="{{ $level->id }}" @selected((int) old('level_id', $student->level_id) === $level->id)>{{ $level->name }} ({{ $level->code }})</option>
                                @endforeach
                            </select>
                            @error('level_id')<p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="student_class_id" class="block text-sm font-medium text-slate-800">{{ __('Class') }}</label>
                            <select id="student_class_id" name="class_id" class="qs-input mt-1 w-full py-2.5">
                                <option value="">{{ __('Unassigned') }}</option>
                                @foreach ($classes as $classroom)
                                    <option value="{{ $classroom->id }}" @selected((int) old('class_id', $student->class_id) === $classroom->id)>{{ $classroom->name }} — {{ $classroom->program?->code }} / {{ $classroom->level?->code ?? $classroom->level?->name }}</option>
                                @endforeach
                            </select>
                            @error('class_id')<p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Account status') }}</h2>
                    <div class="mt-4 rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3">
                        <input type="hidden" name="is_active" value="0" />
                        <label class="inline-flex cursor-pointer items-start gap-3">
                            <input type="checkbox" name="is_active" value="1" class="mt-1 rounded border-slate-300 text-slate-800 focus:ring-slate-400" @checked((string) old('is_active', $student->is_active ? '1' : '0') === '1') />
                            <span>
                                <span class="block text-sm font-medium text-slate-800">{{ __('Active') }}</span>
                                <span class="block text-xs text-slate-500">{{ __('Active students must have a class. Inactive students may be left unassigned.') }}</span>
                            </span>
                        </label>
                    </div>
                    @error('is_active')<p class="mt-2 text-sm text-qs-danger">{{ $message }}</p>@enderror
                </div>

                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Password') }}</h2>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Use one click to generate a fresh student password.') }}</p>
                    <div class="mt-4 rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3">
                        <input type="hidden" name="generate_password" value="0" />
                        <label class="inline-flex cursor-pointer items-start gap-3">
                            <input type="checkbox" name="generate_password" value="1" class="mt-1 rounded border-slate-300 text-slate-800 focus:ring-slate-400" @checked((string) old('generate_password', '0') === '1') />
                            <span>
                                <span class="block text-sm font-medium text-slate-800">{{ __('Generate new password on save') }}</span>
                                <span class="block text-xs text-slate-500">{{ __('A random password will be created and displayed once after saving.') }}</span>
                            </span>
                        </label>
                    </div>
                    @error('generate_password')<p class="mt-2 text-sm text-qs-danger">{{ $message }}</p>@enderror
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:justify-end">
                    <a href="{{ route('coordinator.students.index') }}" class="qs-btn-secondary inline-flex min-h-[44px] items-center justify-center px-4 text-sm font-semibold">{{ __('Cancel') }}</a>
                    <button type="submit" class="qs-btn-primary min-h-[44px] px-5 text-sm font-semibold">{{ __('Save changes') }}</button>
                </div>
            </form>
        </div>
    </details>

    <div class="mt-5 rounded-xl border border-rose-200/70 bg-rose-50/60 p-4">
        <p class="text-sm font-semibold text-rose-800">{{ __('Remove student account') }}</p>
        <p class="mt-1 text-xs text-rose-700">{{ __('Deletion is only allowed when the student is not assigned to a class and has no exam or practice data.') }}</p>
        <form method="POST" action="{{ route('coordinator.students.destroy', $student) }}" class="mt-3" onsubmit="return confirm('{{ __('Delete this student account? This action cannot be undone.') }}');">
            @csrf
            @method('DELETE')
            <button type="submit" class="inline-flex min-h-[40px] items-center justify-center rounded-lg border border-rose-200 bg-white px-4 text-xs font-semibold text-rose-700 hover:bg-rose-100">
                {{ __('Delete student') }}
            </button>
        </form>
    </div>
</x-layouts.coordinator>
