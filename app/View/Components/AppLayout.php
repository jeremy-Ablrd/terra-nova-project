<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /** Titre de la page (<title>) ; sans valeur, seul le nom de l'application est affiché. */
    public function __construct(public ?string $title = null, public bool $banniere = true) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.app');
    }
}
