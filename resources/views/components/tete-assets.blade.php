@php
    // Mode économie de données : le navigateur envoie « Save-Data: on ». Le script n'est alors pas chargé du tout
    // (sauf sur le profil, dont la confirmation de suppression en a besoin) et les règles ci-dessous montrent les menus.
    $sobre = request()->header('Save-Data') === 'on' && ! request()->routeIs('profile.*');

    // Sans JavaScript (ou en mode économie), les menus repliés par Alpine restent accessibles : le menu mobile et le
    // menu du compte sont affichés, le bouton qui les ouvrait est masqué.
    $sansScript = '#menu-mobile{display:block!important}nav [x-show]{display:block!important}button[aria-controls="menu-mobile"]{display:none!important}';
@endphp
{{-- Icône intégrée (SVG en data:) : évite la requête vers /favicon.ico. --}}
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Ccircle cx='8' cy='8' r='7' fill='%231f2937'/%3E%3C/svg%3E">

{{-- Polices du système : aucune police téléchargée. Un seul script, en différé (type="module"). --}}
@vite($sobre ? ['resources/css/app.css'] : ['resources/css/app.css', 'resources/js/app.js'])

@if ($sobre)
    <style>{!! $sansScript !!}</style>
@else
    <noscript><style>{!! $sansScript !!}</style></noscript>
@endif
