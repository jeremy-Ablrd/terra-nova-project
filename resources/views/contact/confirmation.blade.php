<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">{{ __('Votre demande a bien été envoyée') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <section role="status" class="bg-green-50 border border-green-200 overflow-hidden border border-gray-200 rounded-xl p-8 text-center">
                <p class="text-sm text-green-900">{{ __('Numéro de référence') }}</p>
                <p class="mt-2 text-4xl font-bold tracking-wide text-gray-900">{{ $demande->reference }}</p>
                <p class="mt-4 text-sm text-green-900">{{ __('Conservez cette référence pour suivre votre demande') }}</p>
            </section>

            <section class="tn-card overflow-hidden p-6" aria-labelledby="recapitulatif">
                <h2 id="recapitulatif" class="text-lg font-semibold text-gray-900">{{ __('Récapitulatif') }}</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-3 text-sm">
                    <div>
                        <dt class="text-gray-500">{{ __('Objet') }}</dt>
                        <dd class="mt-1 text-gray-900">{{ $demande->objet }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('Service concerné') }}</dt>
                        <dd class="mt-1 text-gray-900">{{ $demande->service?->nom ?? __('À orienter par la mairie') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('Envoyée le') }}</dt>
                        <dd class="mt-1 text-gray-900">{{ \App\Support\DateLocale::format($demande->created_at) }}</dd>
                    </div>
                </dl>
            </section>

            <nav aria-label="{{ __('Suite de votre demande') }}" class="flex flex-wrap items-center gap-4">
                <x-primary-link href="{{ route('demandes.show', $demande) }}">{{ __('Voir ma demande') }}</x-primary-link>
                <a href="{{ route('demandes.index') }}" class="text-sm underline text-gray-700 hover:text-gray-900">{{ __('Mes demandes') }}</a>
                <a href="{{ route('contact.create') }}" class="text-sm underline text-gray-700 hover:text-gray-900">{{ __('Envoyer une autre demande') }}</a>
            </nav>
        </div>
    </div>
</x-app-layout>
