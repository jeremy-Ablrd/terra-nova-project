<?php

namespace App\Http\Controllers;

use App\Enums\TypeContribution;
use App\Models\Contribution;
use App\Models\Projet;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Contribuer (F65, F66, F68, F76) : un avis sur un projet (ce n'est PAS un vote), une idée, un commentaire sur un service.
 * Réservé au citoyen (role:citoyen). Le compte, le type, le projet ou le service et le statut sont fixés côté serveur ;
 * la contribution est figée une fois envoyée et jamais affichée publiquement.
 */
class ParticipationController extends Controller
{
    public function avisCreate(Request $request, Projet $projet): View|RedirectResponse
    {
        abort_unless($projet->estPublie(), 404);

        if (! $projet->consultationOuverte()) {
            return $this->consultationFermee($projet);
        }

        return view('participation.formulaire', [
            'type' => TypeContribution::Avis,
            'deja' => $this->existante($request->user(), TypeContribution::Avis, projet: $projet),
            'action' => route('projets.avis.store', $projet),
            'titrePage' => __('Donner mon avis'),
            'sousTitre' => $projet->titre,
            'retour' => ['label' => __('Retour au projet'), 'url' => route('projets.show', $projet)],
            'fil' => [['label' => __('Projets de la ville'), 'url' => route('projets.index')], ['label' => $projet->titre, 'url' => route('projets.show', $projet)], ['label' => __('Donner mon avis')]],
        ]);
    }

    public function avisStore(Request $request, Projet $projet): RedirectResponse
    {
        abort_unless($projet->estPublie(), 404);

        if (! $projet->consultationOuverte()) {
            return $this->consultationFermee($projet);
        }

        $donnees = $request->validate(['message' => ['required', 'string', 'min:10', 'max:1000']], [], ['message' => __('votre avis')]);

        return $this->enregistrer($request, TypeContribution::Avis, $donnees, projet: $projet);
    }

    public function ideeCreate(): View
    {
        return view('participation.formulaire', [
            'type' => TypeContribution::Idee,
            'deja' => null,
            'action' => route('idees.store'),
            'titrePage' => __('Proposer une idée'),
            'sousTitre' => null,
            'retour' => ['label' => __('Retour aux projets'), 'url' => route('projets.index')],
            'fil' => [['label' => __('Projets de la ville'), 'url' => route('projets.index')], ['label' => __('Proposer une idée')]],
        ]);
    }

    public function ideeStore(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'titre' => ['required', 'string', 'min:5', 'max:120'],
            'message' => ['required', 'string', 'min:20', 'max:1500'],
        ], [], ['titre' => __('titre de l\'idée'), 'message' => __('description de l\'idée')]);

        return $this->enregistrer($request, TypeContribution::Idee, $donnees);
    }

    public function commentaireCreate(Request $request, Service $service): View
    {
        abort_unless($service->actif, 404);

        return view('participation.formulaire', [
            'type' => TypeContribution::Commentaire,
            'deja' => $this->existante($request->user(), TypeContribution::Commentaire, service: $service),
            'action' => route('services.commentaire.store', $service),
            'titrePage' => __('Laisser un commentaire'),
            'sousTitre' => $service->nom,
            'retour' => ['label' => __('Retour au service'), 'url' => route('services.show', $service)],
            'fil' => [['label' => __('Services municipaux'), 'url' => route('services.index')], ['label' => $service->nom, 'url' => route('services.show', $service)], ['label' => __('Laisser un commentaire')]],
        ]);
    }

    public function commentaireStore(Request $request, Service $service): RedirectResponse
    {
        abort_unless($service->actif, 404);

        $donnees = $request->validate(['message' => ['required', 'string', 'min:10', 'max:1000']], [], ['message' => __('votre commentaire')]);

        return $this->enregistrer($request, TypeContribution::Commentaire, $donnees, service: $service);
    }

    /** Contribution déjà déposée par cet habitant (un avis par projet, un commentaire par service). */
    private function existante(User $user, TypeContribution $type, ?Projet $projet = null, ?Service $service = null): ?Contribution
    {
        if ($type === TypeContribution::Idee) {
            return null;
        }

        return Contribution::where('user_id', $user->id)->where('type', $type->value)
            ->where('projet_id', $projet?->id)->where('service_id', $service?->id)->first();
    }

    private function enregistrer(Request $request, TypeContribution $type, array $donnees, ?Projet $projet = null, ?Service $service = null): RedirectResponse
    {
        $user = $request->user();

        $resultat = DB::transaction(function () use ($user, $type, $donnees, $projet, $service) {
            // Le compte est verrouillé : deux envois simultanés ne peuvent pas créer deux avis pour le même projet.
            User::whereKey($user->id)->lockForUpdate()->first();

            if ($deja = $this->existante($user, $type, $projet, $service)) {
                return $deja;
            }

            $contribution = new Contribution;
            $contribution->fill($donnees);          // titre et message seulement (Fillable)
            $contribution->user_id = $user->id;      // fixé côté serveur
            $contribution->type = $type;
            $contribution->projet_id = $projet?->id;
            $contribution->service_id = $service?->id;
            $contribution->save();                   // l'événement `created` génère la référence et l'étape « reçue »

            return $contribution;
        });

        if (! $resultat->wasRecentlyCreated) {
            return redirect()->route('mes-contributions.show', $resultat)
                ->withErrors(['participation' => $type === TypeContribution::Avis
                    ? __('Vous avez déjà donné votre avis sur ce projet : il est figé et la ville le lit.')
                    : __('Vous avez déjà laissé un commentaire sur ce service : il est figé et la ville le lit.')]);
        }

        return redirect()->route('mes-contributions.show', $resultat)
            ->with('success', __('Votre contribution est enregistrée. Référence : :reference.', ['reference' => $resultat->reference]));
    }

    private function consultationFermee(Projet $projet): RedirectResponse
    {
        return redirect()->route('projets.show', $projet)
            ->withErrors(['participation' => __('La consultation de ce projet n\'est pas ouverte : il n\'est pas possible de donner un avis pour le moment.')]);
    }
}
