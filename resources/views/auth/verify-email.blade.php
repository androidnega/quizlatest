<x-guest-layout>
    <div class="mb-4 text-sm text-qs-text">
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 rounded-lg border border-qs-soft bg-qs-card px-4 py-3 text-sm font-medium text-qs-text">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <div>
                <x-primary-button>{{ __('Resend Verification Email') }}</x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="qs-btn-secondary text-sm">{{ __('Log Out') }}</button>
        </form>
    </div>
</x-guest-layout>
