<?php

use App\Http\Controllers\AccueilController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AlerteController as AdminAlerteController;
use App\Http\Controllers\Admin\CompteController;
use App\Http\Controllers\Admin\ParticipationController as AdminParticipationController;
use App\Http\Controllers\Admin\ProjetController as AdminProjetController;
use App\Http\Controllers\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Admin\SecuriteController as AdminSecuriteController;
use App\Http\Controllers\Admin\SynchronisationController;
use App\Http\Controllers\Agent\DemandeController as AgentDemandeController;
use App\Http\Controllers\Agent\DonneesApiController;
use App\Http\Controllers\Agent\JournalController;
use App\Http\Controllers\AccessibiliteController;
use App\Http\Controllers\AccuseReceptionController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\AlerteController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\MesContributionsController;
use App\Http\Controllers\ParticipationController;
use App\Http\Controllers\ProjetController;
use App\Http\Controllers\EcoConceptionController;
use App\Http\Controllers\MesConnexionsController;
use App\Http\Controllers\MesDonneesController;
use App\Http\Controllers\DemandeController;
use App\Http\Controllers\PreferenceAffichageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecapitulatifDemandesController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SuppressionCompteController;
use App\Http\Controllers\UrgenceController;
use Illuminate\Support\Facades\Route;

Route::get('/', AccueilController::class)->name('accueil');

// Alertes en cours : pages publiques (visibles sans connexion).
Route::get('/alertes', [AlerteController::class, 'index'])->name('alertes.index');
Route::get('/alertes/{alerte}', [AlerteController::class, 'show'])->name('alertes.show');

// Catalogue des services municipaux : pages publiques (visibles sans connexion).
Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
Route::get('/services/{service:slug}', [ServiceController::class, 'show'])->name('services.show');

// Projets de la ville (F67) : pages publiques (visibles sans connexion) ; contribuer exige un compte citoyen (ci-dessous).
Route::get('/projets', [ProjetController::class, 'index'])->name('projets.index');
Route::get('/projets/{projet:slug}', [ProjetController::class, 'show'])->name('projets.show');

// Accessibilité : page publique (lien dans le pied de page) et réglages d'affichage (taille du texte, thème),
// ouverts à tous les visiteurs : cookie, et compte si l'utilisateur est connecté.
Route::get('/accessibilite', [AccessibiliteController::class, 'index'])->name('accessibilite');

// Sécurité : ce qui protège le compte, et ce que la plateforme ne fait pas (page publique).
Route::view('/securite', 'securite')->name('securite');

// Éco-conception (F57 à F60) : mesures de poids et choix de sobriété, page publique.
Route::get('/eco-conception', [EcoConceptionController::class, 'index'])->name('eco-conception');
Route::post('/preferences/affichage', [PreferenceAffichageController::class, 'update'])->middleware('throttle:60,1')->name('preferences.affichage');

// Hôpitaux et services d'urgence : une seule page publique (F46).
Route::get('/urgences', [UrgenceController::class, 'index'])->name('urgences.index');

Route::get('/espace', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Contacter la mairie : réservé au citoyen (l'agent et l'admin reçoivent un 403).
Route::middleware(['auth', 'role:citoyen'])->group(function () {
    Route::get('/contact', [ContactController::class, 'create'])->name('contact.create');
    Route::post('/contact', [ContactController::class, 'store'])->middleware(['formulaire:reference', 'throttle:5,1'])->name('contact.store');
    Route::get('/contact/confirmation/{demande}', [ContactController::class, 'confirmation'])->name('contact.confirmation');
});

// Participation (F65, F66, F68, F76) : avis (ce n'est pas un vote), idées, commentaires sur un service, et « Mes contributions ».
// Réservé au citoyen (agent et admin : 403, invité : connexion). Envois limités par compte (`contributions`).
Route::middleware(['auth', 'role:citoyen'])->group(function () {
    Route::get('/projets/{projet:slug}/avis', [ParticipationController::class, 'avisCreate'])->name('projets.avis.create');
    Route::post('/projets/{projet:slug}/avis', [ParticipationController::class, 'avisStore'])->middleware(['formulaire:reference', 'throttle:contributions'])->name('projets.avis.store');
    Route::get('/idees/nouvelle', [ParticipationController::class, 'ideeCreate'])->name('idees.create');
    Route::post('/idees', [ParticipationController::class, 'ideeStore'])->middleware(['formulaire:reference', 'throttle:contributions'])->name('idees.store');
    Route::get('/services/{service:slug}/commentaire', [ParticipationController::class, 'commentaireCreate'])->name('services.commentaire.create');
    Route::post('/services/{service:slug}/commentaire', [ParticipationController::class, 'commentaireStore'])->middleware(['formulaire:reference', 'throttle:contributions'])->name('services.commentaire.store');
    Route::get('/mes-contributions', [MesContributionsController::class, 'index'])->name('mes-contributions.index');
    Route::get('/mes-contributions/{contribution}', [MesContributionsController::class, 'show'])->name('mes-contributions.show');
});

// Mes données (F55, F56, F33) : réservé au citoyen (agent et admin : 403, invité : connexion). Ces routes fixes sont
// déclarées avant /mes-demandes/{demande} pour ne pas être prises pour un identifiant de demande.
Route::middleware(['auth', 'role:citoyen'])->group(function () {
    Route::get('/mes-donnees', [MesDonneesController::class, 'index'])->name('mes-donnees.index');
    Route::get('/mes-donnees/dossier', [MesDonneesController::class, 'dossier'])->middleware('throttle:donnees-telechargement')->name('mes-donnees.dossier');
    Route::get('/mes-donnees/dossier/telecharger', [MesDonneesController::class, 'dossierTelecharger'])->middleware('throttle:donnees-telechargement')->name('mes-donnees.dossier.telecharger');
    Route::get('/mes-demandes/recapitulatif/telecharger', [RecapitulatifDemandesController::class, 'telecharger'])->middleware('throttle:donnees-telechargement')->name('demandes.recapitulatif.telecharger');
    Route::get('/mes-demandes/recapitulatif', [RecapitulatifDemandesController::class, 'recapitulatif'])->middleware('throttle:donnees-telechargement')->name('demandes.recapitulatif');
    // Accusé de réception d'une demande (F83) : habitant propriétaire seulement (DemandePolicy::accuserReception).
    Route::get('/mes-demandes/{demande}/accuse', [AccuseReceptionController::class, 'show'])->name('demandes.accuse');
    Route::get('/mes-demandes/{demande}/accuse/telecharger', [AccuseReceptionController::class, 'telecharger'])->middleware('throttle:donnees-telechargement')->name('demandes.accuse.telecharger');
    // Suppression du compte en deux étapes, sans JavaScript : information, puis mot de passe et case à cocher.
    Route::get('/mes-donnees/suppression', [SuppressionCompteController::class, 'information'])->name('mes-donnees.suppression');
    Route::get('/mes-donnees/suppression/confirmer', [SuppressionCompteController::class, 'confirmation'])->name('mes-donnees.suppression.confirmer');
    Route::delete('/mes-donnees/suppression', [SuppressionCompteController::class, 'destroy'])->middleware('throttle:suppression-compte')->name('mes-donnees.suppression.destroy');
});

// Page publique affichée après la suppression d'un compte.
Route::get('/compte-supprime', [SuppressionCompteController::class, 'termine'])->name('compte-supprime');

// Outils réservés aux agents (l'admin n'y a pas accès).
Route::middleware(['auth', 'role:agent'])->prefix('agent')->group(function () {
    Route::get('/', [AgentController::class, 'index'])->name('agent.index');
    Route::get('/demandes', [AgentDemandeController::class, 'index'])->name('agent.demandes.index');
    // Données de l'API (D19) : lecture seule ; l'actualisation reste sur /admin/synchronisation. Le POST ne touche qu'une préférence de l'agent.
    Route::get('/donnees-api', [DonneesApiController::class, 'index'])->name('agent.donnees-api.index');
    Route::post('/donnees-api/vues', [DonneesApiController::class, 'marquerVues'])->middleware('throttle:10,1')->name('agent.donnees-api.vues');
    Route::get('/demandes/{demande}', [AgentDemandeController::class, 'show'])->name('agent.demandes.show');
    // Seul le changement de statut (via TransitionDemande) : jamais de statut libre.
    Route::patch('/demandes/{demande}/statut', [AgentDemandeController::class, 'updateStatut'])->name('agent.demandes.statut');
    // Journal d'activité : lecture seule (aucune route d'écriture, de modification ni de suppression).
    Route::get('/journal', [JournalController::class, 'index'])->name('agent.journal.index');
});

// Administration : admin uniquement.
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('admin.index');
    Route::get('/comptes', [CompteController::class, 'index'])->name('admin.comptes.index');
    Route::post('/comptes/{user}/role', [CompteController::class, 'updateRole'])->name('admin.comptes.role');
    // Journal de sécurité (F37, F54, F70) : lecture seule, admin seul.
    Route::get('/securite', [AdminSecuriteController::class, 'index'])->name('admin.securite');
    Route::get('/synchronisation', [SynchronisationController::class, 'index'])->name('admin.synchronisation.index');
    Route::post('/synchronisation', [SynchronisationController::class, 'store'])->middleware('throttle:6,1')->name('admin.synchronisation.run');

    // Catalogue : disponibilité et mise en avant des services (admin seul : role:admin + ServicePolicy).
    Route::get('/services', [AdminServiceController::class, 'index'])->name('admin.services.index');
    Route::get('/services/{service}/modifier', [AdminServiceController::class, 'edit'])->name('admin.services.edit');
    Route::put('/services/{service}', [AdminServiceController::class, 'update'])->name('admin.services.update');
    // Coupure d'urgence (F63) : désactiver / réactiver depuis la liste, sans JavaScript, avec limite de débit.
    Route::post('/services/{service}/desactiver', [AdminServiceController::class, 'desactiver'])->middleware('throttle:20,1')->name('admin.services.desactiver');
    Route::post('/services/{service}/reactiver', [AdminServiceController::class, 'reactiver'])->middleware('throttle:20,1')->name('admin.services.reactiver');

    // Participation (F65, F66, F67, F68, F76) : admin seul. Les agents n'y ont aucun accès. Routes fixes avant {contribution}.
    Route::get('/participation', [AdminParticipationController::class, 'index'])->name('admin.participation.index');
    Route::get('/participation/projets', [AdminProjetController::class, 'index'])->name('admin.participation.projets.index');
    Route::get('/participation/projets/nouveau', [AdminProjetController::class, 'create'])->name('admin.participation.projets.create');
    Route::post('/participation/projets', [AdminProjetController::class, 'store'])->middleware('formulaire')->name('admin.participation.projets.store');
    Route::get('/participation/projets/{projet}/modifier', [AdminProjetController::class, 'edit'])->name('admin.participation.projets.edit');
    Route::put('/participation/projets/{projet}', [AdminProjetController::class, 'update'])->name('admin.participation.projets.update');
    Route::get('/participation/{contribution}', [AdminParticipationController::class, 'show'])->whereNumber('contribution')->name('admin.participation.show');
    // Seul le statut suivant (via TransitionContribution) : jamais de statut libre, jamais de suppression.
    Route::patch('/participation/{contribution}/statut', [AdminParticipationController::class, 'statut'])->whereNumber('contribution')->middleware('throttle:30,1')->name('admin.participation.statut');

    // Publication des alertes : admin seul (role:admin du groupe).
    Route::get('/alertes', [AdminAlerteController::class, 'index'])->name('admin.alertes.index');
    Route::get('/alertes/nouvelle', [AdminAlerteController::class, 'create'])->name('admin.alertes.create');
    Route::post('/alertes', [AdminAlerteController::class, 'store'])->middleware('formulaire')->name('admin.alertes.store');
    Route::post('/alertes/{alerte}/terminer', [AdminAlerteController::class, 'terminer'])->name('admin.alertes.terminer');
});

Route::middleware('auth')->group(function () {

    // Historique : réservé au citoyen (agent et admin : 403). Le détail ci-dessous reste régi par DemandePolicy::view.
    // Mes connexions (F54) : son propre compte, quel que soit le rôle.
    Route::get('/mes-connexions', [MesConnexionsController::class, 'index'])->name('mes-connexions.index');
    Route::post('/mes-connexions/deconnexion', [MesConnexionsController::class, 'deconnexionGlobale'])->middleware('throttle:10,1')->name('mes-connexions.deconnexion');
    Route::delete('/mes-connexions/appareils/{appareil}', [MesConnexionsController::class, 'oublier'])->name('mes-connexions.oublier');
    Route::post('/mes-connexions/{evenement}/vu', [MesConnexionsController::class, 'vu'])->name('mes-connexions.vu');
    Route::post('/mes-connexions/{evenement}/pas-moi', [MesConnexionsController::class, 'pasMoi'])->middleware('throttle:10,1')->name('mes-connexions.pas-moi');

    Route::get('/mes-demandes', [DemandeController::class, 'index'])->middleware('role:citoyen')->name('demandes.index');
    Route::get('/mes-demandes/{demande}', [DemandeController::class, 'show'])->name('demandes.show');
    Route::post('/mes-demandes/etapes/{etape}/vu', [DemandeController::class, 'acquitter'])->name('demandes.etapes.vu');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
