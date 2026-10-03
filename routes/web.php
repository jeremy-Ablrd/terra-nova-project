<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AlerteController as AdminAlerteController;
use App\Http\Controllers\Admin\CompteController;
use App\Http\Controllers\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Admin\SynchronisationController;
use App\Http\Controllers\Agent\DemandeController as AgentDemandeController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\AlerteController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DemandeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Alertes en cours : pages publiques (visibles sans connexion).
Route::get('/alertes', [AlerteController::class, 'index'])->name('alertes.index');
Route::get('/alertes/{alerte}', [AlerteController::class, 'show'])->name('alertes.show');

// Catalogue des services municipaux : pages publiques (visibles sans connexion).
Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
Route::get('/services/{service:slug}', [ServiceController::class, 'show'])->name('services.show');

Route::get('/espace', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Contacter la mairie : réservé au citoyen (l'agent et l'admin reçoivent un 403).
Route::middleware(['auth', 'role:citoyen'])->group(function () {
    Route::get('/contact', [ContactController::class, 'create'])->name('contact.create');
    Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');
    Route::get('/contact/confirmation/{demande}', [ContactController::class, 'confirmation'])->name('contact.confirmation');
});

// Outils réservés aux agents (l'admin n'y a pas accès).
Route::middleware(['auth', 'role:agent'])->prefix('agent')->group(function () {
    Route::get('/', [AgentController::class, 'index'])->name('agent.index');
    Route::get('/demandes', [AgentDemandeController::class, 'index'])->name('agent.demandes.index');
});

// Administration : admin uniquement.
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('admin.index');
    Route::get('/comptes', [CompteController::class, 'index'])->name('admin.comptes.index');
    Route::post('/comptes/{user}/role', [CompteController::class, 'updateRole'])->name('admin.comptes.role');
    Route::get('/synchronisation', [SynchronisationController::class, 'index'])->name('admin.synchronisation.index');
    Route::post('/synchronisation', [SynchronisationController::class, 'store'])->middleware('throttle:6,1')->name('admin.synchronisation.run');

    // Catalogue : disponibilité et mise en avant des services (admin seul : role:admin + ServicePolicy).
    Route::get('/services', [AdminServiceController::class, 'index'])->name('admin.services.index');
    Route::get('/services/{service}/modifier', [AdminServiceController::class, 'edit'])->name('admin.services.edit');
    Route::put('/services/{service}', [AdminServiceController::class, 'update'])->name('admin.services.update');

    // Publication des alertes : admin seul (role:admin du groupe).
    Route::get('/alertes', [AdminAlerteController::class, 'index'])->name('admin.alertes.index');
    Route::get('/alertes/nouvelle', [AdminAlerteController::class, 'create'])->name('admin.alertes.create');
    Route::post('/alertes', [AdminAlerteController::class, 'store'])->name('admin.alertes.store');
    Route::post('/alertes/{alerte}/terminer', [AdminAlerteController::class, 'terminer'])->name('admin.alertes.terminer');
});

Route::middleware('auth')->group(function () {

    Route::get('/mes-demandes', [DemandeController::class, 'index'])->name('demandes.index');
    Route::get('/mes-demandes/{demande}', [DemandeController::class, 'show'])->name('demandes.show');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
