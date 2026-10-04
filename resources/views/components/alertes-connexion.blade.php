@php
    $alertes = app(\App\Services\AppareilsConnus::class)->alertesNonVues(auth()->user());
@endphp

{{-- F54 : une alerte par nouvelle connexion non vue. Deux boutons (formulaires POST, sans JavaScript) : « C'était moi » / « Ce n'était pas moi ». --}}
@foreach ($alertes as $alerte)
    <div role="status" class="tn-banner tn-banner--info max-w-none flex-col">
        <p class="text-sm font-semibold"><span aria-hidden="true">◆ </span>
            {{ __('Nouvelle connexion le :date à :heure depuis :appareil', [
                'date' => \App\Support\DateLocale::jourMois($alerte->created_at),
                'heure' => \App\Support\DateLocale::heure($alerte->created_at),
                'appareil' => $alerte->detail,
            ]) }}
        </p>
        <p class="text-sm">{{ __('Si c\'est vous, vous n\'avez rien à faire. Sinon, nous déconnectons les autres appareils et vous invitons à changer votre mot de passe.') }}</p>
        <div class="flex flex-wrap gap-3">
            <form method="POST" action="{{ route('mes-connexions.vu', $alerte) }}">
                @csrf
                <x-secondary-button type="submit">{{ __('C\'était moi') }}</x-secondary-button>
            </form>
            <form method="POST" action="{{ route('mes-connexions.pas-moi', $alerte) }}">
                @csrf
                <button type="submit" class="tn-btn tn-btn--danger">{{ __('Ce n\'était pas moi') }}</button>
            </form>
        </div>
    </div>
@endforeach
