<?php

/**
 * Participation (F65, F66, F67, F68, F76) : phrases de suivi lues par l'habitant. Un avis n'est PAS un vote.
 * Ne jamais nommer ce fichier comme un mot seul utilisé avec __('Mot') (sous Windows, « Participation » chargerait participation.php).
 */
return [
    'statut' => [
        'recue' => 'La ville a bien reçu votre contribution.',
        'examinee' => 'Les équipes de la ville ont lu et examiné votre contribution.',
        'prise_en_compte' => 'Votre contribution a été prise en compte : la ville vous a répondu.',
    ],
    'dossier' => [
        'aucune' => 'Vous n\'avez encore déposé aucune contribution (avis, idée ou commentaire).',
        'total' => '{1} Vous avez déposé :count contribution.|[2,*] Vous avez déposé :count contributions.',
        'prises_en_compte' => '{0} Aucune n\'est encore prise en compte.|{1} :count est prise en compte par la ville.|[2,*] :count sont prises en compte par la ville.',
    ],
];
