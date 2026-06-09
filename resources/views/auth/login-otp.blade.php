<x-guest-layout
    :page-title="__('Verify code')"
    :show-header="false"
    :eyebrow="__('Verification')"
    :heading="__('Enter your one-time code')"
    :description="__('Check the SMS on your phone. The code expires in a few minutes.')"
>
    <form id="otp-form" method="POST" action="{{ url('/login/otp') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="otp-digit-0" :value="__('One-time code')" />
            <input id="otp" name="otp" type="hidden" value="{{ old('otp', '') }}" required />
            <div class="qs-auth-otp" id="otp-digits">
                @for ($i = 0; $i < 6; $i++)
                    <input
                        id="otp-digit-{{ $i }}"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="1"
                        class="qs-auth-otp-box"
                        autocomplete="one-time-code"
                        @if ($i === 0) autofocus @endif
                    />
                @endfor
            </div>
            <x-input-error :messages="$errors->get('otp')" class="mt-2" />
        </div>

        <div class="qs-auth-actions">
            <a href="{{ route('login', ['restart' => 1]) }}" class="qs-btn-secondary justify-center">
                {{ __('Back') }}
            </a>
            <button type="submit" class="qs-btn-primary flex-1 justify-center sm:flex-none sm:min-w-[8rem]">
                {{ __('Sign in') }}
            </button>
        </div>
    </form>
</x-guest-layout>

<script>
    (() => {
        const form = document.getElementById('otp-form');
        const hiddenOtp = document.getElementById('otp');
        const boxes = Array.from({ length: 6 }, (_, i) => document.getElementById(`otp-digit-${i}`));

        if (!form || !hiddenOtp || boxes.some((b) => !b)) return;

        function focusFirstEmpty() {
            const idx = boxes.findIndex((b) => !b.value);
            const target = idx === -1 ? boxes[5] : boxes[idx];
            target.focus();
            target.select();
        }

        function syncOtpValue() {
            const otp = boxes.map((b) => String(b.value || '').replace(/\D/g, '').slice(0, 1)).join('');
            hiddenOtp.value = otp;
            return otp;
        }

        function fillFrom(index, digits) {
            let writeIndex = index;
            for (const digit of digits) {
                if (writeIndex > 5) break;
                boxes[writeIndex].value = digit;
                writeIndex += 1;
            }
            const otp = syncOtpValue();
            const next = Math.min(writeIndex, 5);
            boxes[next].focus();
            boxes[next].select();
            if (otp.length === 6) form.requestSubmit();
        }

        const prefill = String(hiddenOtp.value || '').replace(/\D/g, '').slice(0, 6);
        if (prefill.length) fillFrom(0, prefill.split(''));
        else focusFirstEmpty();

        boxes.forEach((box, index) => {
            box.addEventListener('focus', () => box.select());

            box.addEventListener('input', (event) => {
                const digits = String(event.target.value || '').replace(/\D/g, '');
                if (!digits) {
                    event.target.value = '';
                    syncOtpValue();
                    return;
                }
                event.target.value = '';
                fillFrom(index, digits.split(''));
            });

            box.addEventListener('keydown', (event) => {
                if (event.key === 'Backspace') {
                    if (!box.value && index > 0) {
                        boxes[index - 1].value = '';
                        boxes[index - 1].focus();
                        boxes[index - 1].select();
                        syncOtpValue();
                        event.preventDefault();
                    }
                    return;
                }
                if (event.key === 'ArrowLeft' && index > 0) {
                    event.preventDefault();
                    boxes[index - 1].focus();
                    boxes[index - 1].select();
                }
                if (event.key === 'ArrowRight' && index < 5) {
                    event.preventDefault();
                    boxes[index + 1].focus();
                    boxes[index + 1].select();
                }
            });

            box.addEventListener('paste', (event) => {
                event.preventDefault();
                const pasted = String(event.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0, 6);
                if (!pasted) return;
                fillFrom(index, pasted.split(''));
            });
        });
    })();
</script>
