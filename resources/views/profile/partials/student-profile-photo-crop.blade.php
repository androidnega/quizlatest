<div
    id="student-profile-photo-crop"
    class="qs-profile-photo-block"
    data-error-too-large="{{ __('Please choose an image under 8 MB before cropping.') }}"
    data-error-max-size="{{ __('Cropped photo must be 250 KB or less. Zoom out or use a simpler image.') }}"
>
    <div class="flex flex-wrap items-center gap-4">
        <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-[1.4rem] border border-slate-200 bg-slate-100 text-lg font-bold text-slate-600">
            @if (filled($user->face_image_path))
                <img
                    data-photo-preview
                    src="{{ route('profile.face-image') }}"
                    alt=""
                    class="h-full w-full object-cover"
                    width="96"
                    height="96"
                />
            @else
                <img data-photo-preview src="" alt="" class="hidden h-full w-full object-cover" width="96" height="96" />
                <span data-photo-initials>{{ \Illuminate\Support\Str::of((string) $user->name)->trim()->explode(' ')->filter()->take(2)->map(fn ($w) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($w, 0, 1)))->implode('') }}</span>
            @endif
        </div>
        <div class="min-w-0 flex-1 space-y-3">
            <p class="text-xs text-slate-500">{{ __('Drag to reposition and pinch or scroll to zoom. Your face should sit inside the square frame.') }}</p>
            <div class="flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    data-photo-choose
                    class="inline-flex items-center justify-center rounded-lg bg-[#EF3340] px-4 py-2 text-xs font-semibold text-white hover:bg-[#D91F2D]"
                >
                    {{ __('Choose photo') }}
                </button>
                @if (filled($user->face_image_path))
                    <form method="post" action="{{ route('profile.update') }}" class="inline">
                        @csrf
                        @method('patch')
                        <input type="hidden" name="phone" value="{{ old('phone', $user->phone) }}" />
                        <button
                            type="submit"
                            name="remove_profile_photo"
                            value="1"
                            class="text-xs font-semibold text-rose-700 hover:text-rose-900"
                            onclick="return confirm(@json(__('Remove your profile photo?')))"
                        >{{ __('Remove photo') }}</button>
                    </form>
                @endif
            </div>
            <p class="text-xs text-slate-500">{{ __('Saved as JPEG, max 250 KB.') }}</p>
            <x-input-error class="mt-1" :messages="$errors->get('profile_photo')" />
            <p data-crop-error class="hidden text-xs font-medium text-rose-700"></p>
        </div>
    </div>

    <input data-photo-picker type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" />

    <form data-photo-form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="hidden">
        @csrf
        @method('patch')
        <input type="hidden" name="phone" value="{{ old('phone', $user->phone) }}" />
        <input data-photo-file-input type="file" name="profile_photo" accept="image/jpeg" />
    </form>

    <div
        data-crop-modal
        class="fixed inset-0 z-[80] hidden flex items-end justify-center bg-[#101828]/60 p-4 sm:items-center"
        aria-hidden="true"
        role="dialog"
        aria-modal="true"
        aria-labelledby="profile-crop-title"
    >
        <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="border-b border-slate-200 px-4 py-3">
                <h3 id="profile-crop-title" class="text-sm font-semibold text-slate-900">{{ __('Adjust your photo') }}</h3>
                <p class="mt-0.5 text-xs text-slate-500">{{ __('Move and zoom so your head fits inside the frame.') }}</p>
            </div>
            <div class="max-h-[min(60vh,420px)] bg-slate-900">
                <img data-crop-image src="" alt="" class="block max-h-[min(60vh,420px)] w-full" />
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-200 px-4 py-3">
                <button type="button" data-crop-cancel class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    {{ __('Cancel') }}
                </button>
                <button type="button" data-crop-save class="rounded-lg bg-[#EF3340] px-4 py-2 text-xs font-semibold text-white hover:bg-[#D91F2D]">
                    {{ __('Save photo') }}
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    @vite('resources/js/studentProfilePhotoCrop.js')
@endpush
