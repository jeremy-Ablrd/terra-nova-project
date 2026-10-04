<x-app-layout>
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Mon espace'), 'url' => route('dashboard')],
            ['label' => __('Mes données'), 'url' => route('mes-donnees.index')],
            ['label' => __('Supprimer mon compte'), 'url' => route('mes-donnees.suppression')],
            ['label' => __('Confirmation')],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">{{ __('Confirmer la suppression de mon compte') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <p class="px-4 sm:px-0 text-sm text-gray-800"><strong>{{ __('Étape 2 sur 2') }}</strong> — {{ __('pour vous protéger, saisissez votre mot de passe.') }}</p>

            @if ($errors->any())
                <div role="alert" class="rounded border-2 border-red-800 bg-white p-3 text-sm font-medium text-red-900">
                    <p>{{ __('Votre compte n\'a pas été supprimé. Corrigez :') }}</p>
                    <ul class="list-disc ps-5">
                        @foreach ($errors->all() as $erreur)
                            <li>{{ $erreur }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('mes-donnees.suppression.destroy') }}" class="bg-white border border-gray-200 rounded-xl p-6 space-y-5">
                @csrf
                @method('DELETE')

                <div>
                    <x-input-label for="password" :value="__('Votre mot de passe')" />
                    <x-text-input id="password" name="password" type="password" class="mt-1 block w-full sm:w-80" required autocomplete="current-password"
                                  aria-describedby="{{ $errors->has('password') ? 'password-erreur' : '' }}" />
                    <x-input-error id="password-erreur" :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div>
                    <div class="flex items-start gap-3">
                        <input id="comprends" name="comprends" type="checkbox" value="1" class="mt-1 rounded border-gray-500"
                               aria-describedby="{{ $errors->has('comprends') ? 'comprends-erreur' : '' }}">
                        <label for="comprends" class="text-sm text-gray-900">{{ __('Je comprends que cette action est définitive') }}</label>
                    </div>
                    <x-input-error id="comprends-erreur" :messages="$errors->get('comprends')" class="mt-2" />
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <button type="submit" class="tn-btn tn-btn--danger">{{ __('Supprimer définitivement mon compte') }}</button>
                    <a href="{{ route('mes-donnees.index') }}" class="underline text-gray-900 text-sm">{{ __('Annuler et revenir') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
