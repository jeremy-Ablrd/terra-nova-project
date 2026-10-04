@php($protection = app(\App\Services\ProtectionFormulaires::class))

{{-- F81, F82 : à placer juste après @csrf dans chaque formulaire protégé par le middleware `formulaire`. Rien n'est affiché
     quand la protection est désactivée. Le champ leurre est caché, hors tabulation et masqué aux lecteurs d'écran. --}}
@if ($protection->active())
    <input type="hidden" name="{{ \App\Services\ProtectionFormulaires::CHAMP_JETON }}" value="{{ $protection->jeton() }}">
    <div class="hidden" aria-hidden="true">
        <label for="leurre-{{ $protection->nomLeurre() }}">{{ __('Ne remplissez pas ce champ') }}</label>
        <input type="text" id="leurre-{{ $protection->nomLeurre() }}" name="{{ $protection->nomLeurre() }}" value="" tabindex="-1" autocomplete="off">
    </div>
    @if ($errors->has('formulaire'))
        <p role="alert" class="tn-banner tn-banner--warn max-w-none"><span aria-hidden="true">▲</span> {{ $errors->first('formulaire') }}</p>
    @endif
@endif
