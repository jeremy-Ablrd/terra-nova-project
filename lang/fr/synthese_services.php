<?php

// Synthèse de l'état des services (F63, F64) : accords singulier/pluriel dans un seul fichier. Nom volontairement différent de « services » : sous Windows, __('Services') chargerait ce fichier (système de fichiers insensible à la casse).
return [
    'etat' => [
        'disponibles' => '{0} aucun service disponible|{1} :count service disponible|[2,*] :count services disponibles',
        'interrompus' => '{1} :count interrompu|[2,*] :count interrompus',
        'desactives' => '{1} :count désactivé|[2,*] :count désactivés',
        'tous_disponibles' => '{1} Le service est disponible.|[2,*] Les :count services sont tous disponibles.',
        'aucun_service' => 'Aucun service n\'est renseigné pour le moment.',
    ],
];
