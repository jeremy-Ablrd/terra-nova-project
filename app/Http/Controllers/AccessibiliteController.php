<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/** Page publique « Accessibilité » : ce qui est disponible pour lire et naviguer confortablement. */
class AccessibiliteController extends Controller
{
    public function index(): View
    {
        return view('accessibilite');
    }
}
