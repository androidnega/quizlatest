<x-guest-layout
    content-max="max-w-3xl"
    :show-header="false"
    :page-title="__('Complete your profile')"
    :eyebrow="__('First-time setup')"
    :heading="__('Finish enrolling your account')"
    :description="__('Complete the two quick steps below.')"
>
    <form
        id="onboarding-form"
        method="POST"
        action="{{ route('student.onboarding.store') }}"
        class="space-y-8"
        x-data="{
            step: {{ (int) old('step', (int) ($draft['step'] ?? 1)) }},
            maxStep: 2,
            stepError: '',
            passwordValue: '',
            passwordConfirmationValue: '',
            passwordMatchState() {
                if (!this.passwordValue && !this.passwordConfirmationValue) return 'idle';
                if (!this.passwordValue || !this.passwordConfirmationValue) return 'typing';
                return this.passwordValue === this.passwordConfirmationValue ? 'match' : 'mismatch';
            },
            nextStep() {
                this.stepError = '';
                if (this.step === 1) {
                    const name = document.getElementById('name');
                    if (name?.hasAttribute('required') && !String(name.value || '').trim()) {
                        this.stepError = '{{ __('Please enter your full name to continue.') }}';
                        return;
                    }
                }
                if (this.step === 2) {
                    const p1 = document.getElementById('password');
                    const p2 = document.getElementById('password_confirmation');
                    const v1 = String(p1?.value || '');
                    const v2 = String(p2?.value || '');
                    if (!v1 || !v2) {
                        this.stepError = '{{ __('Enter and confirm your password to continue.') }}';
                        return;
                    }
                    if (v1 !== v2) {
                        this.stepError = '{{ __('Passwords do not match.') }}';
                        return;
                    }
                    if (v1.length < 8) {
                        this.stepError = '{{ __('Use at least 8 characters for your password.') }}';
                        return;
                    }
                }
                this.step = Math.min(this.maxStep, this.step + 1);
                window.__onboardingSaveStep?.(this.step);
            },
            prevStep() {
                this.stepError = '';
                this.step = Math.max(1, this.step - 1);
                window.__onboardingSaveStep?.(this.step);
            }
        }"
        data-onboarding-user-id="{{ $user->id }}"
    >
        @csrf

        <div class="mb-3 sm:hidden">
            <div class="flex items-center justify-between text-xs font-semibold text-qs-muted">
                <span>{{ __('Step') }} <span x-text="step"></span> {{ __('of') }} <span x-text="maxStep"></span></span>
                <span x-text="step === 1 ? '{{ __('Profile') }}' : '{{ __('Password') }}'"></span>
            </div>
            <div class="mt-2 h-1.5 rounded-full bg-slate-200">
                <div class="h-1.5 rounded-full bg-emerald-600 transition-all duration-200" :style="`width: ${(step / maxStep) * 100}%`"></div>
            </div>
        </div>

        <div class="mb-2 hidden flex-wrap gap-2 sm:flex">
            <span :class="step >= 1 ? 'bg-emerald-100 text-emerald-900 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'" class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold">{{ __('Step 1') }} · {{ __('Profile') }}</span>
            <span :class="step >= 2 ? 'bg-emerald-100 text-emerald-900 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'" class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold">{{ __('Step 2') }} · {{ __('Password') }}</span>
        </div>

        <section x-show="step === 1" x-cloak class="grid gap-6 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-input-label for="name" :value="__('Full name')" />
                <input id="name" name="name" type="text" value="{{ old('name', $draft['name'] ?? $user->name) }}" class="qs-input" placeholder="{{ __('Full name') }}" @if (trim((string) $user->name) === '') required @endif />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>
        </section>

        <section x-show="step === 2" x-cloak class="grid gap-6 sm:grid-cols-2">
            <div>
                <x-input-label for="password" :value="__('Password')" />
                <input id="password" name="password" type="password" x-model="passwordValue" x-bind:required="step === 2" autocomplete="new-password" class="qs-input" placeholder="{{ __('Create password') }}" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="password_confirmation" :value="__('Confirm password')" />
                <input id="password_confirmation" name="password_confirmation" type="password" x-model="passwordConfirmationValue" x-bind:required="step === 2" autocomplete="new-password" class="qs-input" placeholder="{{ __('Confirm password') }}" />
                <p x-show="passwordMatchState() === 'typing'" class="mt-2 text-xs text-qs-muted">{{ __('Keep typing to confirm your password.') }}</p>
                <p x-show="passwordMatchState() === 'match'" class="mt-2 text-xs font-medium text-emerald-700">{{ __('Passwords match.') }}</p>
                <p x-show="passwordMatchState() === 'mismatch'" class="mt-2 text-xs font-medium text-rose-700">{{ __('Passwords do not match yet.') }}</p>
            </div>
        </section>

        <input type="hidden" name="step" id="onboarding_step" value="{{ (int) old('step', (int) ($draft['step'] ?? 1)) }}" />

        <p x-show="stepError" x-text="stepError" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"></p>

        <div class="flex flex-wrap gap-3">
            <button type="button" x-show="step > 1" @click="prevStep()" class="qs-btn-secondary">
                {{ __('Back') }}
            </button>
            <button type="button" x-show="step < maxStep" @click="nextStep()" class="qs-btn-secondary">
                {{ __('Next') }}
            </button>
            <button type="submit" x-show="step === maxStep" class="qs-btn-primary">
                {{ __('Complete setup and sign in') }}
            </button>
        </div>
    </form>
    <script>
        (function () {
            const form = document.getElementById('onboarding-form');
            const nameInput = document.getElementById('name');
            const pwdInput = document.getElementById('password');
            const pwdConfInput = document.getElementById('password_confirmation');
            const stepInput = document.getElementById('onboarding_step');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            let draftSaveTimer = null;

            function setCurrentStep(step) {
                if (!form) return;
                const stack = form._x_dataStack;
                if (Array.isArray(stack) && stack[0] && typeof stack[0].step !== 'undefined') {
                    stack[0].step = step;
                }
                if (stepInput) stepInput.value = String(step);
            }

            function saveDraft(patch) {
                if (!form || !csrfToken) return;
                if (draftSaveTimer) window.clearTimeout(draftSaveTimer);
                draftSaveTimer = window.setTimeout(async function () {
                    try {
                        await fetch(@json(route('student.onboarding.draft')), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                Accept: 'application/json',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                step: Number(patch && patch.step),
                                name: typeof patch?.name === 'string' ? patch.name : undefined,
                            }),
                        });
                    } catch (_) {
                        //
                    }
                }, 450);
            }

            window.__onboardingSaveStep = function (step) {
                const normalizedStep = Number(step) || 1;
                if (stepInput) stepInput.value = String(normalizedStep);
                saveDraft({ step: normalizedStep, name: nameInput?.value || '' });
            };

            form?.addEventListener('submit', function (e) {
                const p1 = String(pwdInput?.value || '');
                const p2 = String(pwdConfInput?.value || '');
                if (!p1 || !p2) {
                    e.preventDefault();
                    setCurrentStep(2);
                    return;
                }
                if (p1 !== p2) {
                    e.preventDefault();
                    setCurrentStep(2);
                    return;
                }
            });

            [nameInput, pwdInput, pwdConfInput].forEach(function (el) {
                if (!el) return;
                el.addEventListener('input', function () {
                    saveDraft({ name: nameInput?.value || '' });
                });
            });
        })();
    </script>
</x-guest-layout>
