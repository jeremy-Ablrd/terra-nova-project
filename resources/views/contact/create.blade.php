<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">{{ __('Contacter les services municipaux') }}</h1>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden border border-gray-200 rounded-xl p-6">
                <p class="text-sm text-gray-600">
                    {{ __('Une question ou une difficulté ? Écrivez-nous : votre message sera transmis aux services de Terra Nova et vous recevrez un numéro de référence.') }}
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
                        @php
                            $interrompus = $services->filter->estInterrompu();
                            $desactives = $services->filter->estDesactive();
                            $decrit = trim(($interrompus->isNotEmpty() ? 'services_interrompus ' : '').($desactives->isNotEmpty() ? 'services_desactives ' : '').($errors->has('service_id') ? 'service_id_erreur' : ''));
                        @endphp
                        <select id="service_id" name="service_id"
                                class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                @if ($errors->has('service_id')) aria-invalid="true" @endif
                                @if ($decrit !== '') aria-describedby="{{ $decrit }}" @endif>
                            <option value="">{{ __('Je ne sais pas, laissez la mairie orienter ma demande') }}</option>
                            @foreach ($services as $service)
                                <option value="{{ $service->id }}" @selected(! $service->estDesactive() && (string) old('service_id', request()->query('service_id')) === (string) $service->id)@disabled($service->estDesactive())>{{ $service->nom }}@if ($service->estInterrompu()) — {{ __('service interrompu') }}@elseif ($service->estDesactive()) — {{ __('service désactivé') }}@endif</option>
                            @endforeach
                        </select>
                        @if ($interrompus->isNotEmpty())
                            <div id="services_interrompus" class="service-interrompu mt-2 p-3 text-sm text-gray-900">
                                <p class="font-semibold">{{ __('Services actuellement interrompus') }}</p>
                                <p class="mt-1">{{ __('Vous pouvez quand même envoyer votre demande : elle sera traitée dès le retour du service.') }}</p>
                                <ul class="mt-2 space-y-2">
                                    @foreach ($interrompus as $service)
                                        <li>
                                            <span class="font-medium">{{ $service->nom }}</span> — {{ __('service interrompu') }}.
                                            @if ($service->motif_interruption) {{ $service->motif_interruption }} @endif
                                            {{ __('Retour estimé :') }} {{ $service->retour_estime_at ? \App\Support\DateLocale::format($service->retour_estime_at) : __('non communiqué') }}.
                                            @if ($service->alternative) {{ __('À faire en attendant :') }} {{ $service->alternative }} @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @if ($desactives->isNotEmpty())
                            <div id="services_desactives" class="service-desactive mt-2 p-3 text-sm text-gray-900">
                                <p class="font-semibold">{{ __('Services actuellement désactivés') }}</p>
                                <p class="mt-1">{{ __('Ces services ne peuvent pas être choisis pour le moment. Choisissez un autre service, ou « Je ne sais pas » : la mairie orientera votre demande.') }}</p>
                                <ul class="mt-2 space-y-2">
                                    @foreach ($desactives as $service)
                                        <li>
                                            <span class="font-medium">{{ $service->nom }}</span> — {{ __('service désactivé') }}.
                                            @if ($service->motif_interruption) {{ $service->motif_interruption }} @endif
                                            @if ($service->alternative) {{ __('À faire à la place :') }} {{ $service->alternative }} @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
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
