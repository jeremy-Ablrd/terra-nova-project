<x-document-autonome :titre="__('Accusé de réception')" :sous-titre="__('Demande :reference auprès de la ville de Terra Nova.', ['reference' => $a['reference']])">
    @include('demandes._accuse')
</x-document-autonome>
