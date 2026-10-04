{{-- Suppression du compte : un seul parcours, en deux étapes sans JavaScript, sur la page « Mes données ». Citoyen seulement. --}}
<section class="space-y-3">
    <header>
        <h2 class="text-lg font-medium text-gray-900">{{ __('Supprimer mon compte') }}</h2>
        <p class="mt-1 text-sm text-gray-600">{{ __('Vous verrez d\'abord ce qui sera supprimé et ce qui sera conservé. Rien n\'est supprimé avant votre confirmation.') }}</p>
    </header>

    <a href="{{ route('mes-donnees.suppression') }}" class="tn-btn tn-btn--danger">{{ __('Supprimer mon compte') }}</a>
</section>
