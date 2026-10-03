{{-- Pied de page commun à tous les layouts : repère <footer> et lien vers la page Accessibilité. --}}
<footer class="mt-auto border-t border-gray-200 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-wrap items-center justify-between gap-x-6 gap-y-2 text-sm text-gray-700">
        <p>{{ config('app.name') }}</p>
        <nav aria-label="{{ __('Liens du pied de page') }}">
            <a href="{{ route('accessibilite') }}" class="underline hover:text-gray-900">{{ __('Accessibilité') }}</a>
        </nav>
    </div>
</footer>
