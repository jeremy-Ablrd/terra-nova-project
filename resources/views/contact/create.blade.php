<x-app-layout>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Contacter les services municipaux') }}</h1>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-600">
                    {{ __('Une question ou une difficulté ? Écrivez-nous : votre message sera transmis aux services de Nova Terra et vous recevrez un numéro de référence.') }}
                </p>
                <p class="mt-1 text-sm text-gray-600">{{ __('Les champs marqués d\'un * sont obligatoires.') }}</p>

                <form method="POST" action="{{ route('contact.store') }}" class="mt-6 space-y-6" novalidate
                      x-data="{ envoi: false }" x-on:submit="envoi = true" x-on:pageshow.window="envoi = false">
                    @csrf

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <x-input-label for="contact_nom" :value="__('Nom')" />
                            <x-text-input id="contact_nom" class="block mt-1 w-full bg-gray-50" type="text" :value="Auth::user()->name" readonly aria-describedby="contact_identite_aide" />
                        </div>
                        <div>
                            <x-input-label for="contact_email" :value="__('Adresse e-mail')" />
                            <x-text-input id="contact_email" class="block mt-1 w-full bg-gray-50" type="email" :value="Auth::user()->email" readonly aria-describedby="contact_identite_aide" />
                        </div>
                    </div>
                    <p id="contact_identite_aide" class="-mt-3 text-xs text-gray-500">
                        {{ __('Ces informations viennent de votre compte. Vous pouvez les modifier depuis votre profil.') }}
                    </p>

                    <div>
                        <x-input-label for="service_id" :value="__('Service concerné')" />
                        <select id="service_id" name="service_id"
                                class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                @if ($errors->has('service_id')) aria-invalid="true" aria-describedby="service_id_erreur" @endif>
                            <option value="">{{ __('Je ne sais pas, laissez la mairie orienter ma demande') }}</option>
                            @foreach ($services as $service)
                                <option value="{{ $service->id }}" @selected((string) old('service_id') === (string) $service->id)>{{ $service->nom }}</option>
                            @endforeach
                        </select>
                        <x-input-error id="service_id_erreur" :messages="$errors->get('service_id')" class="mt-2" role="alert" />
                    </div>

                    <div>
                        <x-input-label for="objet" :value="__('Objet').' *'" />
                        <x-text-input id="objet" name="objet" class="block mt-1 w-full" type="text" :value="old('objet')"
                                      maxlength="150" required aria-required="true"
                                      aria-describedby="objet_aide{{ $errors->has('objet') ? ' objet_erreur' : '' }}"
                                      :aria-invalid="$errors->has('objet') ? 'true' : 'false'" />
                        <p id="objet_aide" class="mt-1 text-xs text-gray-500">{{ __('150 caractères maximum.') }}</p>
                        <x-input-error id="objet_erreur" :messages="$errors->get('objet')" class="mt-2" role="alert" />
                    </div>

                    <div>
                        <x-input-label for="message" :value="__('Message').' *'" />
                        <textarea id="message" name="message" rows="7" minlength="10" maxlength="3000" required aria-required="true"
                                  aria-describedby="message_aide{{ $errors->has('message') ? ' message_erreur' : '' }}"
                                  aria-invalid="{{ $errors->has('message') ? 'true' : 'false' }}"
                                  class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('message') }}</textarea>
                        <p id="message_aide" class="mt-1 text-xs text-gray-500">{{ __('Entre 10 et 3 000 caractères.') }}</p>
                        <x-input-error id="message_erreur" :messages="$errors->get('message')" class="mt-2" role="alert" />
                    </div>

                    <div class="flex items-center justify-end">
                        <x-primary-button x-bind:disabled="envoi" x-bind:aria-disabled="envoi" class="disabled:opacity-50 disabled:cursor-not-allowed">
                            <span x-text="envoi ? {{ Js::from(__('Envoi en cours…')) }} : {{ Js::from(__('Envoyer ma demande')) }}">{{ __('Envoyer ma demande') }}</span>
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
