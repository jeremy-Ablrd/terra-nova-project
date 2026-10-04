<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /** Titre de la page (<title>) ; $flash : afficher le message de confirmation de session (non utile sur l'accueil) ; sans valeur, seul le nom de l'application est affiché. */
    public function __construct(public ?string $title = null, public bool $banniere = true, public bool $flash = true) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.app');
    }
}
