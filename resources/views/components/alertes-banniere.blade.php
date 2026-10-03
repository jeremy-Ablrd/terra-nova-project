@if ($alertes->isNotEmpty())
    {{-- Bandeau des alertes en cours : le niveau est écrit en toutes lettres, la mise en forme s'y ajoute sans le remplacer.
         role="alert" seulement pour l'urgent (annoncé tout de suite), role="status" sinon. --}}
    <aside aria-label="{{ __('Alertes en cours') }}" class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 space-y-3">
            @foreach ($alertes->take(3) as $alerte)
                <section role="{{ $alerte->niveau->role() }}" aria-labelledby="alerte-{{ $alerte->id }}"
                         class="alerte {{ $alerte->niveau->classe() }} p-3 text-sm text-gray-900">
                    <p class="font-semibold">
                        <span class="uppercase tracking-wide">{{ $alerte->niveau->label() }}</span>
                        — <span id="alerte-{{ $alerte->id }}">{{ $alerte->titre }}</span>
                    </p>
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

            @if ($alertes->count() > 3)
                <p class="text-sm">
                    <a href="{{ route('alertes.index') }}" class="underline font-medium">{{ __('Voir toutes les alertes') }} ({{ $alertes->count() }})</a>
                </p>
            @endif
        </div>
    </aside>
@endif
