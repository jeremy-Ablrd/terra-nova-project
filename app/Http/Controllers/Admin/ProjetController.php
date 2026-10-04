<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActionJournal;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjetRequest;
use App\Models\Projet;
use App\Services\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Projets de la ville : création, modification, publication (admin seul). Jamais de suppression : un projet se retire en
 * décochant « Publié ». Le journal ne cite que « Projet n° id » et les NOMS des champs modifiés, jamais un texte.
 */
class ProjetController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Projet::class);

        return view('admin.participation.projets.index', ['projets' => Projet::latest()->latest('id')->paginate(20)]);
    }

    public function create(): View
    {
        Gate::authorize('create', Projet::class);

        return view('admin.participation.projets.form', ['projet' => new Projet, 'debut' => '', 'fin' => '']);
    }

    public function store(StoreProjetRequest $request): RedirectResponse
    {
        Gate::authorize('create', Projet::class);

        $projet = DB::transaction(function () use ($request) {
            $projet = new Projet;
            $projet->fill($request->safe()->except('publie'));
            $projet->slug = $this->slugUnique($projet->titre);   // fixé côté serveur
            $projet->publie_at = $request->boolean('publie') ? now() : null;
            $projet->save();

            Journal::enregistrer($request->user(), ActionJournal::ProjetCree, $projet, $projet->publie_at ? 'projet publié' : 'projet en brouillon');

            return $projet;
        });

        return redirect()->route('admin.participation.projets.index')
            ->with('success', __('Le projet « :titre » est enregistré.', ['titre' => $projet->titre]));
    }

    public function edit(Projet $projet): View
    {
        Gate::authorize('update', $projet);

        // Valeurs des champs datetime-local (heure locale, sans secondes) : calculées ici, jamais dans la vue.
        return view('admin.participation.projets.form', [
            'projet' => $projet,
            'debut' => $projet->consultation_debut_at?->format('Y-m-d\TH:i') ?? '',
            'fin' => $projet->consultation_fin_at?->format('Y-m-d\TH:i') ?? '',
        ]);
    }

    public function update(StoreProjetRequest $request, Projet $projet): RedirectResponse
    {
        Gate::authorize('update', $projet);

        DB::transaction(function () use ($request, $projet) {
            $projet = Projet::whereKey($projet->id)->lockForUpdate()->firstOrFail();
            $avantPublie = $projet->publie_at !== null;

            $anciennes = $projet->only(['consultation_debut_at', 'consultation_fin_at']);
            $projet->fill($request->safe()->except('publie'));
            // Le formulaire n'a pas de secondes : une date inchangée à la minute près n'est pas une modification.
            foreach ($anciennes as $champ => $ancienne) {
                $nouvelle = $projet->{$champ};
                if ($ancienne !== null && $nouvelle !== null && $ancienne->format('Y-m-d H:i') === $nouvelle->format('Y-m-d H:i')) {
                    $projet->{$champ} = $ancienne;
                }
            }
            $publie = $request->boolean('publie');
            if ($publie && ! $avantPublie) {
                $projet->publie_at = now();
            } elseif (! $publie) {
                $projet->publie_at = null;
            }

            $champs = array_values(array_diff(array_keys($projet->getDirty()), ['publie_at', 'updated_at']));
            $publicationChangee = $avantPublie !== ($projet->publie_at !== null);

            if ($champs !== [] || $publicationChangee) {
                $projet->save();
                Journal::enregistrer($request->user(), ActionJournal::ProjetModifie, $projet, Journal::detailProjet($champs, $publicationChangee ? [$avantPublie, ! $avantPublie] : null));
            }
        });

        return redirect()->route('admin.participation.projets.index')->with('success', __('Le projet est mis à jour.'));
    }

    /** Slug lisible et unique : titre-du-projet, titre-du-projet-2, … */
    private function slugUnique(string $titre): string
    {
        $base = Str::slug($titre) ?: 'projet';
        $slug = $base;
        for ($i = 2; Projet::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
