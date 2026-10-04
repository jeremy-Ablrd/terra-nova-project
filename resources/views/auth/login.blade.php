<x-guest-layout>
    <h1 class="font-display font-bold text-3xl text-gray-900 mb-6">{{ __('Connexion') }}</h1>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-2 border-gray-300 bg-white text-brand-text focus:ring-accent" name="remember">
                <span class="ms-2 text-sm text-gray-900">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-900 hover:text-gray-600" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="ms-4">
                {{ __('Log in') }}
            </x-primary-button>
        </div>

        <p class="mt-6 pt-6 border-t border-gray-200 text-center text-sm text-gray-600">
            Pas encore de compte ?
            <a class="font-semibold underline text-gray-900 hover:text-gray-600" href="{{ route('register') }}">Créer mon compte</a>
        </p>
    </form>
</x-guest-layout>
