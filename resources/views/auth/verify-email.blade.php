<x-guest-layout>
    <h1 class="font-display font-bold text-3xl text-gray-900 mb-6">{{ __('Vérifiez votre adresse e-mail') }}</h1>

    <div class="mb-4 text-sm text-gray-600">
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="tn-banner tn-banner--success max-w-none mb-4 text-sm font-semibold">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('Resend Verification Email') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="underline text-sm text-gray-900 hover:text-gray-600">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
