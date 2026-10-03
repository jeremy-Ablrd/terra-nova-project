<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\View\View;

/** Où se trouvent les hôpitaux et services d'urgence ? Une seule page, visible sans connexion (F46). */
class UrgenceController extends Controller
{
    public function index(): View
    {
        // UNE requête : services d'urgence et de santé actifs, urgences d'abord.
        return view('urgences.index', ['services' => Service::actifs()->urgences()->get()]);
    }
}
