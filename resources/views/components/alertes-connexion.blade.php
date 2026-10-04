@php
    $alertes = app(\App\Services\AppareilsConnus::class)->alertesNonVues(auth()->user());
@endphp

{{-- F54 : une alerte par nouvelle connexion non vue. Deux boutons (formulaires POST, sans JavaScript) : « C'était moi » / « Ce n'était pas moi ». --}}
@foreach ($alertes as $alerte)
    <div role="status" class="rounded-lg border-2 border-gray-800 bg-white p-4 space-y-3">
        <p class="text-sm font-medium text-gray-900">
            {{ __('Nouvelle connexion le :date à :heure depuis :appareil', [
                'date' => \App\Support\DateLocale::jourMois($alerte->created_at),
                'heure' => \App\Support\DateLocale::heure($alerte->created_at),
                'appareil' => $alerte->detail,
            ]) }}
        </p>
        <p class="text-sm text-gray-700">{{ __('Si c\'est vous, vous n\'avez rien à faire. Sinon, nous déconnectons les autres appareils et vous invitons à changer votre mot de passe.') }}</p>
        <div class="flex flex-wrap gap-3">
            <form method="POST" action="{{ route('mes-connexions.vu', $alerte) }}">
                @csrf
                <x-secondary-button type="submit">{{ __('C\'était moi') }}</x-secondary-button>
            </form>
            <form method="POST" action="{{ route('mes-connexions.pas-moi', $alerte) }}">
                @csrf
                <button type="submit" class="btn btn-danger">{{ __('Ce n\'était pas moi') }}</button>
            </form>
        </div>
    </div>
@endforeach
