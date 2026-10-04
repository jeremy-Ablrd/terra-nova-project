{{-- Carte du design (tn-card) : conteneur de contenu sur fond relevé. --}}
<div {{ $attributes->merge(['class' => 'tn-card']) }}>
    {{ $slot }}
</div>
