<?php

namespace App\View\Components;

use App\Services\AlertesActives;
use Illuminate\View\Component;
use Illuminate\View\View;

/** Bandeau global des alertes en cours, visible de tous (connectés ou non). */
class AlertesBanniere extends Component
{
    public function __construct(private readonly AlertesActives $actives) {}

    public function render(): View
    {
        return view('components.alertes-banniere', ['alertes' => $this->actives->get()]);
    }
}
