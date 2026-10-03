{{-- Pied de page commun à tous les layouts : repère <footer> et liens vers les pages Accessibilité et Éco-conception. --}}
<footer class="mt-auto border-t border-gray-200 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-wrap items-center justify-between gap-x-6 gap-y-2 text-sm text-gray-700">
        <p>{{ config('app.name') }}</p>
        <nav aria-label="{{ __('Liens du pied de page') }}">
            <a href="{{ route('accessibilite') }}" class="underline hover:text-gray-900">{{ __('Accessibilité') }}</a>
            <span aria-hidden="true">·</span>
            <a href="{{ route('eco-conception') }}" class="underline hover:text-gray-900">{{ __('Éco-conception') }}</a>
            <span aria-hidden="true">·</span>
            <a href="{{ route('securite') }}" class="underline hover:text-gray-900">{{ __('Sécurité') }}</a>
        </nav>
    </div>
</footer>
