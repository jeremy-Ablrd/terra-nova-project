<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class AgentController extends Controller
{
    public function index(): View
    {
        return view('agent.index');
    }
}
