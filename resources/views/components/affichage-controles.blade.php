@php
    $taille = \App\Support\Affichage::taille();
    $theme = \App\Support\Affichage::theme();
@endphp

{{-- Réglages d'affichage : taille du texte et thème. Formulaire POST sans JavaScript ; chaque bouton envoie UNE valeur.
     L'état courant est dit par aria-pressed, par le trait sous le bouton et par un « coché » (jamais la couleur seule). --}}
<div class="bg-gray-100 border-b border-gray-200">
    <form method="POST" action="{{ route('preferences.affichage') }}" aria-label="{{ __("Réglages d'affichage") }}"
          class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
        @csrf

        <div role="group" aria-labelledby="affichage-taille" class="flex flex-wrap items-center gap-2">
            <span id="affichage-taille" class="font-medium text-gray-900">{{ __('Taille du texte') }} :</span>
            @foreach (\App\Enums\TailleTexte::cases() as $option)
                <button type="submit" name="taille" value="{{ $option->value }}"
                        aria-pressed="{{ $taille === $option ? 'true' : 'false' }}"
                        class="bouton-reglage {{ $taille === $option ? 'bouton-reglage-actif' : '' }}">{{ $option->label() }}</button>
            @endforeach
        </div>

        <div role="group" aria-labelledby="affichage-theme" class="flex flex-wrap items-center gap-2">
            <span id="affichage-theme" class="font-medium text-gray-900">{{ __('Thème') }} :</span>
            @foreach (\App\Enums\ThemeAffichage::cases() as $option)
                <button type="submit" name="theme" value="{{ $option->value }}"
                        aria-pressed="{{ $theme === $option ? 'true' : 'false' }}"
                        class="bouton-reglage {{ $theme === $option ? 'bouton-reglage-actif' : '' }}">{{ $option->label() }}</button>
            @endforeach
        </div>
    </form>
</div>
