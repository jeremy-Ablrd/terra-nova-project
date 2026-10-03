@php
    // Toutes les alertes urgentes sont toujours affichées (le tri les met en tête), en plus de 3 autres au maximum.
    $urgentes = $alertes->filter(fn ($a) => $a->niveau === \App\Enums\Niveau::Urgent);
    $affichees = $urgentes->concat($alertes->reject(fn ($a) => $a->niveau === \App\Enums\Niveau::Urgent)->take(3));
@endphp
@if ($alertes->isNotEmpty())
    {{-- Bandeau des alertes en cours : le niveau est écrit en toutes lettres, la mise en forme s'y ajoute sans le remplacer.
         role="alert" seulement pour l'urgent (annoncé tout de suite), role="status" sinon. --}}
    <aside aria-label="{{ __('Alertes en cours') }}" class="no-print bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 space-y-3">
            @foreach ($affichees as $alerte)
                <section role="{{ $alerte->niveau->role() }}" aria-labelledby="alerte-{{ $alerte->id }}"
                         class="alerte {{ $alerte->niveau->classe() }} p-3 text-sm text-gray-900">
                    <p class="font-semibold">
                        <span class="uppercase tracking-wide">{{ $alerte->niveau->label() }}</span>
                        — <span id="alerte-{{ $alerte->id }}">{{ $alerte->titre }}</span>
                    </p>
                    <p class="mt-1 text-xs font-semibold">{{ ($alerte->emetteur ?? \App\Enums\Emetteur::Ville)->phrase() }}</p>
                    <p class="mt-1"><span class="font-medium">{{ __('Ce qui se passe :') }}</span> {{ \Illuminate\Support\Str::limit($alerte->ce_qui_se_passe, 300) }}</p>
                    <p class="mt-1"><span class="font-medium">{{ __("Ce qu'il faut faire :") }}</span> {{ \Illuminate\Support\Str::limit($alerte->ce_quil_faut_faire, 300) }}</p>
                    @if ($alerte->consignes_vulnerables)
                        <p class="mt-1">{{ __('Des consignes pour les personnes vulnérables sont disponibles dans le détail.') }}</p>
                    @endif
                    <p class="mt-2">
                        <a href="{{ route('alertes.show', $alerte) }}" class="underline font-medium">{{ __('Voir le détail') }}<span class="sr-only"> {{ __("de l'alerte") }} {{ $alerte->titre }}</span></a>
                    </p>
                </section>
            @endforeach

            @if ($alertes->count() > $affichees->count())
                <p class="text-sm">
                    <a href="{{ route('alertes.index') }}" class="underline font-medium">{{ __('Voir toutes les alertes') }} ({{ $alertes->count() }})</a>
                </p>
            @endif
        </div>
    </aside>
@endif
