<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use Illuminate\View\View;

class AgentController extends Controller
{
    public function index(): View
    {
        return view('agent.index', ['enAttente' => Demande::enAttente()->count()]);
    }
}
