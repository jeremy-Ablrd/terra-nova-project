@php
    $taille = \App\Support\Affichage::taille();
    $theme = \App\Support\Affichage::theme();
    $nuit = $theme === \App\Enums\ThemeAffichage::Nuit;
    $contraste = $theme === \App\Enums\ThemeAffichage::Contraste;
    $tailleGlyphe = ['normal' => 'text-sm', 'grand' => 'text-lg', 'tres_grand' => 'text-[1.375rem]'];
@endphp

{{-- Réglages d'affichage : taille du texte (A A A) et thème (jour / nuit, contraste renforcé). Formulaire POST sans
     JavaScript ; chaque bouton envoie UNE valeur. L'état est dit par aria-pressed / aria-checked, un cadre épais et un
     « coché » (jamais la couleur seule). Le serveur pose data-theme sur <html> : pas de flash au premier rendu. --}}
<form method="POST" action="{{ route('preferences.affichage') }}" aria-label="{{ __("Réglages d'affichage") }}"
      {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-4 gap-y-2']) }}>
    @csrf

    <div role="group" aria-labelledby="affichage-taille" class="flex items-center gap-1">
        <span id="affichage-taille" class="sr-only">{{ __('Taille du texte') }} :</span>
        @foreach (\App\Enums\TailleTexte::cases() as $option)
            <button type="submit" name="taille" value="{{ $option->value }}"
                    aria-pressed="{{ $taille === $option ? 'true' : 'false' }}"
                    aria-label="{{ __('Taille du texte') }} : {{ $option->label() }}"
                    class="bouton-reglage bouton-taille font-semibold {{ $tailleGlyphe[$option->value] }} {{ $taille === $option ? 'bouton-reglage-actif' : '' }}"><span aria-hidden="true">A</span></button>
        @endforeach
    </div>

    <div role="group" aria-labelledby="affichage-theme" class="flex items-center gap-1">
        <span id="affichage-theme" class="sr-only">{{ __('Thème') }} :</span>
        <svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
        <button type="submit" name="theme" value="{{ $nuit ? 'standard' : 'nuit' }}" role="switch"
                aria-checked="{{ $nuit ? 'true' : 'false' }}" aria-label="{{ __('Thème nuit') }}"
                class="interrupteur"></button>
        <svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
        <button type="submit" name="theme" value="{{ $contraste ? 'standard' : 'contraste' }}"
                aria-pressed="{{ $contraste ? 'true' : 'false' }}"
                class="bouton-reglage text-sm font-semibold {{ $contraste ? 'bouton-reglage-actif' : '' }}"><span aria-hidden="true">◐</span><span class="sr-only 2xl:not-sr-only"> {{ __('Contraste renforcé') }}</span></button>
    </div>
</form>
