<?php

namespace Tests\Feature;

use App\Enums\Statut;
use App\Models\Demande;
use App\Models\DemandeEtape;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** F55 « Mon dossier » et F56 « Récapitulatif de mes demandes » : des documents lisibles d'abord, informatiques ensuite. */
class DossierHabitantTest extends TestCase
{
    use RefreshDatabase;

    private User $camille;

    private User $victor;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-04 12:00:00');
        Model::preventLazyLoading();

        $this->camille = User::factory()->create([
            'name' => 'Camille Habitant', 'email' => 'camille@example.test', 'remember_token' => 'jeton-secret-camille',
            'created_at' => '2026-09-01 08:00:00', 'updated_at' => '2026-09-01 08:00:00',
        ]);
        $this->victor = User::factory()->create(['name' => 'Victor Voisin', 'email' => 'victor@example.test']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Model::preventLazyLoading(false);

        parent::tearDown();
    }

    /** @param  list<array{0: string, 1: string, 2?: bool}>  $etapes  statut, date, vue ? */
    private function demande(User $user, string $objet, Statut $statut, string $cree, ?string $traitee = null, array $etapes = [], ?string $maj = null): Demande
    {
        $demande = Demande::factory()->create([
            'user_id' => $user->id, 'objet' => $objet, 'message' => 'Message de '.$objet, 'statut' => $statut->value,
            'created_at' => $cree, 'updated_at' => $maj ?? $cree, 'traitee_at' => $traitee,
        ])->refresh();

        foreach ($etapes as [$etat, $date, $vue]) {
            $etape = new DemandeEtape;
            $etape->demande_id = $demande->id;
            $etape->statut = Statut::from($etat);
            $etape->agent_nom = 'Agent Secret';
            $etape->vu_at = $vue ? $date : null;
            $etape->created_at = $date;
            $etape->save();
        }

        return $demande;
    }

    /** 5 demandes variées : 2 nouvelles, 1 en cours, 2 traitées (délais de 2 et 4 jours), 2 changements d'état non lus. */
    private function jeuDeDemandes(): void
    {
        $this->demande($this->camille, 'Lampadaire en panne', Statut::Nouvelle, '2026-09-20 10:00:00');
        $this->demande($this->camille, '=1+1', Statut::Nouvelle, '2026-10-01 08:00:00');
        $this->demande($this->camille, 'Arrêt déplacé', Statut::EnCours, '2026-09-25 09:00:00', null,
            [['en_cours', '2026-09-25 10:00:00', false]], '2026-09-25 10:00:00');
        $this->demande($this->camille, 'Inscription bibliothèque', Statut::Traitee, '2026-09-10 10:00:00', '2026-09-12 10:00:00',
            [['en_cours', '2026-09-11 10:00:00', true], ['traitee', '2026-09-12 10:00:00', true]], '2026-09-12 10:00:00');
        $this->demande($this->camille, 'Carte de transport', Statut::Traitee, '2026-09-15 10:00:00', '2026-09-19 10:00:00',
            [['en_cours', '2026-09-16 10:00:00', true], ['traitee', '2026-09-19 10:00:00', false]], '2026-09-19 10:00:00');

        // Les autres : ne doivent jamais apparaître chez Camille.
        $this->demande($this->victor, 'Demande de Victor', Statut::Traitee, '2026-09-05 10:00:00', '2026-09-06 10:00:00',
            [['en_cours', '2026-09-05 12:00:00', true], ['traitee', '2026-09-06 10:00:00', true]]);
        $this->demande($this->victor, 'Autre demande de Victor', Statut::Nouvelle, '2026-08-01 10:00:00');
        $this->demande($this->victor, 'Troisième demande de Victor', Statut::EnCours, '2026-10-03 10:00:00', null, [['en_cours', '2026-10-03 11:00:00', false]], '2026-10-04 11:30:00');
        Demande::factory()->importee('F21', 'Marc Importé')->create(['objet' => 'Demande importée', 'message' => 'Message importé']);
    }

    private function dossier(): string
    {
        return $this->actingAs($this->camille)->get('/mes-donnees/dossier')->assertOk()->getContent();
    }

    // --- Mon dossier (F55) ---

    public function test_the_dossier_has_the_expected_sections_and_exact_figures(): void
    {
        $this->jeuDeDemandes();
        $this->camille->forceFill(['preferences' => ['taille' => 'grand', 'theme' => 'contraste']])->saveQuietly();

        $page = $this->actingAs($this->camille)->get('/mes-donnees/dossier')->assertOk()
            ->assertSeeInOrder(['Qui vous êtes pour la ville', 'Ce que la ville conserve et pourquoi', 'Votre activité', 'Vos préférences', 'Ce que vous pouvez faire'])
            ->assertSee('Camille Habitant')->assertSee('camille@example.test')
            ->assertSee('01/09/2026 08:00')->assertSee('il y a 33 jours')
            ->assertSee('Vous êtes habitant')
            ->assertSee('Vous avez déposé 5 demandes : 2 nouvelles, 1 en cours, 2 traitées.')
            ->assertSeeInOrder(['Nouvelle', '2', 'En cours', '1', 'Traitée', '2', 'Total', '5'])
            ->assertSee('Première demande déposée le 10/09/2026 10:00.')
            ->assertSee('Dernière demande déposée le 01/10/2026 08:00.')
            ->assertSee('en moyenne en 3,0 jours')                                   // (2 + 4) / 2 : seulement les demandes traitées
            ->assertSee('déposée il y a 14 jours')                                   // la plus ancienne « nouvelle » : 20/09 → 04/10
            ->assertSee('Dernière activité sur vos demandes : 01/10/2026 08:00.')
            ->assertSee('Vous avez 2 changements d\'état non lus.')
            ->assertSee('Taille du texte')->assertSee('Grand')->assertSee('Thème')->assertSee('Contraste renforcé')
            ->assertSee('Nom')->assertSee('Adresse e-mail')->assertSee('Inscrit le')
            ->assertSee('Document généré le 04/10/2026 12:00.')
            ->assertSee('Ce document ne contient que vos données.')
            ->assertSee('anonymisées')
            ->assertSee(route('profile.edit'))->assertSee(route('demandes.export-csv'))->assertSee(route('mes-donnees.suppression'))
            ->getContent();

        $this->assertSame(1, substr_count($page, '<h1'));
        $this->assertSame(1, substr_count($page, '<main'));
        $this->assertStringContainsString('<caption', $page);
        $this->assertStringContainsString('scope="col"', $page);
        $this->assertStringContainsString('scope="row"', $page);
        $this->assertStringContainsString('Fil d&#039;Ariane', $page);
        $this->assertStringContainsString('Télécharger mon dossier', $page);
        $this->assertStringContainsString('Ctrl + P', $page);
        $this->assertMatchesRegularExpression('/<button[^>]*\bhidden\b[^>]*>\s*Imprimer/', $page);
    }

    public function test_the_retention_table_comes_from_the_configuration_not_from_the_view(): void
    {
        $page = $this->dossier();
        foreach (config('dossier.conservation') as $ligne) {
            $this->assertStringContainsString(e(__($ligne['donnee'])), $page);
            $this->assertStringContainsString(e(__($ligne['duree'])), $page);
        }
        $this->assertStringContainsString('30 jours', $page);

        config(['dossier.conservation' => [[
            'donnee' => 'Donnée de test', 'finalite' => 'Finalité de test', 'duree' => 'Durée de test',
            'action' => ['route' => 'mes-connexions.index', 'libelle' => 'Lien de test'],
        ]]]);
        $this->actingAs($this->camille)->get('/mes-donnees/dossier')
            ->assertSee('Donnée de test')->assertSee('Finalité de test')->assertSee('Durée de test')->assertSee('Lien de test')
            ->assertSee(route('mes-connexions.index'))
            ->assertDontSee('Les traces de sécurité');
    }

    public function test_the_dossier_without_any_demande_has_a_clean_sentence_and_no_division_by_zero(): void
    {
        $this->actingAs($this->camille)->get('/mes-donnees/dossier')->assertOk()
            ->assertSee('Vous n\'avez encore aucune demande', false)
            ->assertSee('Vous n\'avez aucun changement d\'état non lu.')
            ->assertDontSee('en moyenne')
            ->assertDontSee('Première demande')
            ->assertDontSee('NaN')->assertDontSee('INF');
    }

    public function test_the_average_delay_only_counts_treated_demandes(): void
    {
        $this->demande($this->camille, 'Ouverte', Statut::Nouvelle, '2026-01-01 10:00:00');
        $this->demande($this->camille, 'En cours', Statut::EnCours, '2026-01-01 10:00:00');

        $this->dossier();
        $this->actingAs($this->camille)->get('/mes-donnees/dossier')
            ->assertSee('Aucune demande n\'est encore traitée')
            ->assertDontSee('en moyenne');

        $this->demande($this->camille, 'Fermée', Statut::Traitee, '2026-09-01 12:00:00', '2026-09-04 00:00:00'); // 2,5 jours
        $this->actingAs($this->camille)->get('/mes-donnees/dossier')->assertSee('en moyenne en 2,5 jours');
    }

    public function test_the_dossier_contains_nothing_secret_and_nothing_from_others(): void
    {
        $this->jeuDeDemandes();

        foreach ([$this->dossier(), $this->actingAs($this->camille)->get('/mes-donnees/dossier/telecharger')->getContent()] as $document) {
            foreach ([
                $this->camille->password, 'jeton-secret-camille', 'remember_token', 'Agent Secret',
                'Victor Voisin', 'victor@example.test', 'Demande de Victor', 'Demande importée', 'Marc Importé', 'Message importé',
            ] as $interdit) {
                $this->assertStringNotContainsString($interdit, $document, $interdit);
            }
        }

        // Vérification croisée : le dossier de Victor ne contient rien de Camille.
        $autre = $this->actingAs($this->victor)->get('/mes-donnees/dossier')->getContent();
        $this->assertStringContainsString('Victor Voisin', $autre);
        foreach (['Camille Habitant', 'camille@example.test', 'Lampadaire en panne', 'Agent Secret'] as $interdit) {
            $this->assertStringNotContainsString($interdit, $autre, $interdit);
        }
    }

    public function test_the_downloaded_dossier_is_a_self_contained_html_file(): void
    {
        $this->jeuDeDemandes();

        $reponse = $this->actingAs($this->camille)->get('/mes-donnees/dossier/telecharger')->assertOk();

        $this->assertStringContainsString('text/html', $reponse->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $reponse->headers->get('Content-Disposition'));
        $this->assertStringContainsString('mon-dossier-nova-terra-2026-10-04.html', $reponse->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $reponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $reponse->headers->get('Cache-Control'));

        $this->verifierFichierAutonome($reponse->getContent());
        $this->assertStringContainsString('Qui vous êtes pour la ville', $reponse->getContent());
        $this->assertStringContainsString('Vous avez déposé 5 demandes', $reponse->getContent());
        $this->assertSame(1, substr_count($reponse->getContent(), '<h1'));
    }

    private function verifierFichierAutonome(string $html): void
    {
        $this->assertStringStartsWith('<!DOCTYPE html>', trim($html));
        $this->assertStringContainsString('<style>', $html);
        $this->assertStringContainsString('.document h2', $html);                    // le CSS de resources/css/document.css, en ligne
        foreach (['<script', '<link', ' src=', '<iframe', '<img', 'url(', '@import', 'skip-link', '<nav'] as $interdit) {
            $this->assertStringNotContainsString($interdit, $html, $interdit);
        }

        // Aucune adresse vers un autre domaine : seuls les liens vers la plateforme elle-même.
        preg_match_all('~https?://([^/"\'\s<]+)~', $html, $m);
        foreach ($m[1] as $hote) {
            $this->assertSame(parse_url(config('app.url'), PHP_URL_HOST), preg_replace('/:\d+$/', '', $hote), "domaine externe : {$hote}");
        }
    }

    public function test_another_citizens_demandes_never_change_my_figures(): void
    {
        $this->demande($this->camille, 'Lampadaire', Statut::Nouvelle, '2026-09-20 10:00:00');
        $this->demande($this->camille, 'Carte', Statut::Traitee, '2026-09-10 10:00:00', '2026-09-12 10:00:00',
            [['en_cours', '2026-09-11 10:00:00', true], ['traitee', '2026-09-12 10:00:00', false]], '2026-09-12 10:00:00');

        $service = app(\App\Services\DonneesPersonnelles::class);
        $chiffres = fn () => json_decode(json_encode(collect($service->dossier($this->camille))->except(['lignes', 'genere_le'])->all()), true);
        $avant = $chiffres();
        $pageAvant = $this->actingAs($this->camille)->get('/mes-donnees/dossier')->getContent();

        // Un deuxième citoyen, avec des demandes de tous les statuts, plus anciennes et plus récentes, avec des délais très différents.
        foreach (range(1, 3) as $i) {
            $this->demande($this->victor, "Victor nouvelle {$i}", Statut::Nouvelle, '2026-07-01 10:00:00');
        }
        foreach (range(1, 2) as $i) {
            $this->demande($this->victor, "Victor en cours {$i}", Statut::EnCours, '2026-10-04 09:00:00', null, [['en_cours', '2026-10-04 10:00:00', false]], '2026-10-04 11:59:00');
        }
        foreach (range(1, 4) as $i) {
            $this->demande($this->victor, "Victor traitée {$i}", Statut::Traitee, '2026-06-01 10:00:00', '2026-06-21 10:00:00',
                [['en_cours', '2026-06-02 10:00:00', false], ['traitee', '2026-06-21 10:00:00', false]]);
        }

        // Aucun chiffre ni aucune phrase de Camille ne bouge : total, statuts, délai moyen, attente, activité, changements non lus.
        $this->assertSame($avant, $chiffres());
        $this->assertSame(2, $service->dossier($this->camille)['total']);
        $this->assertSame(['nouvelle' => 1, 'en_cours' => 0, 'traitee' => 1], $service->dossier($this->camille)['par_statut']->all());

        $pageApres = $this->actingAs($this->camille)->get('/mes-donnees/dossier')->getContent();
        $this->assertSame(
            preg_replace('/<meta name="csrf-token"[^>]*>|name="_token" value="[^"]*"|nonce="[^"]*"/', '', $pageAvant),
            preg_replace('/<meta name="csrf-token"[^>]*>|name="_token" value="[^"]*"|nonce="[^"]*"/', '', $pageApres),
        );

        // Et celui de Victor est bien le sien : 11 demandes, 3 nouvelles, 2 en cours, 4 traitées + celles du jeu de base absentes ici.
        $this->actingAs($this->victor)->get('/mes-donnees/dossier')
            ->assertSee('Vous avez déposé 9 demandes : 3 nouvelles, 2 en cours, 4 traitées.')
            ->assertSee('en moyenne en 20,0 jours')
            ->assertDontSee('Lampadaire')->assertDontSee('Carte');
    }

    public function test_absolute_links_of_downloaded_files_start_from_app_url_never_from_the_request_host(): void
    {
        $this->jeuDeDemandes();
        config(['app.url' => 'https://nova-terra.example']);

        foreach (['/mes-donnees/dossier/telecharger', '/mes-demandes/recapitulatif/telecharger'] as $chemin) {
            $html = $this->actingAs($this->camille)->get('http://interne.test'.$chemin)->assertOk()->getContent();

            preg_match_all('~href="(https?://[^"]+)"~', $html, $m);
            if (str_contains($chemin, 'dossier')) {
                $this->assertNotEmpty($m[1], $chemin); // le dossier renvoie vers le profil, la suppression, le récapitulatif…
            }
            foreach ($m[1] as $lien) {
                $this->assertStringStartsWith('https://nova-terra.example/', $lien, $chemin);
            }
            $this->assertStringNotContainsString('interne.test', $html);
            $this->assertStringNotContainsString('localhost', $html);
        }

        // La page du site, elle, garde l'hôte de la requête : le réglage est remis en place après le rendu du fichier.
        $page = $this->actingAs($this->camille)->get('http://interne.test/mes-donnees/dossier')->getContent();
        $this->assertStringContainsString('http://interne.test/mes-donnees/suppression', $page);
        $this->assertStringNotContainsString('nova-terra.example', $page);
    }

    // --- Récapitulatif (F56) ---

    public function test_the_summary_has_a_synthesis_a_table_a_meaning_column_and_chronologies(): void
    {
        $this->jeuDeDemandes();

        $page = $this->actingAs($this->camille)->get('/mes-demandes/recapitulatif')->assertOk()
            ->assertSeeInOrder(['En bref', 'Toutes mes demandes', 'Ce qui s\'est passé pour chaque demande'])
            ->assertSee('Vous avez déposé 5 demandes : 2 nouvelles, 1 en cours, 2 traitées.')
            ->assertSee('déposée il y a 14 jours')
            ->assertSee('en moyenne en 3,0 jours')
            ->assertSee('Dernière activité sur vos demandes : 01/10/2026 08:00.')
            ->assertSee('Ce que cela signifie pour vous')
            ->assertSee('Durée écoulée (jours)')
            ->assertSee('Votre demande est enregistrée, aucun agent ne l\'a encore prise en charge.')
            ->assertSee('Un agent s\'en occupe.')
            ->assertSee('Le dossier est clos.')
            ->assertSee('10/09/2026 10:00 : demande enregistrée')
            ->assertSee('11/09/2026 10:00 : prise en charge par un agent')
            ->assertSee('12/09/2026 10:00 : demande traitée, le dossier est clos')
            ->assertSee('Ce document ne contient que vos données.')
            ->getContent();

        $this->assertSame(1, substr_count($page, '<h1'));
        $this->assertStringContainsString('<caption', $page);
        $this->assertStringContainsString('Télécharger le récapitulatif', $page);
        $this->assertStringContainsString('Ctrl + P', $page);
        $this->assertStringNotContainsString('Agent Secret', $page);
        $this->assertStringNotContainsString('Demande de Victor', $page);
    }

    public function test_elapsed_days_are_exact_per_demande(): void
    {
        $this->jeuDeDemandes();
        $page = $this->actingAs($this->camille)->get('/mes-demandes/recapitulatif')->getContent();

        // Lampadaire (20/09 → 04/10) : 14 jours ; carte de transport (15/09 → 04/10) : 19 jours.
        $this->assertMatchesRegularExpression('/Lampadaire en panne<\/td>.*?<td>14<\/td>/s', $page);
        $this->assertMatchesRegularExpression('/Carte de transport<\/td>.*?<td>19<\/td>/s', $page);
    }

    public function test_the_summary_without_demande_is_clean(): void
    {
        $page = $this->actingAs($this->camille)->get('/mes-demandes/recapitulatif')->assertOk()
            ->assertSee('Vous n\'avez encore aucune demande', false)
            ->assertDontSee('en moyenne')->assertDontSee('NaN')
            ->getContent();

        $this->assertStringNotContainsString('<table', $page);
    }

    public function test_the_downloaded_summary_is_a_self_contained_html_file(): void
    {
        $this->jeuDeDemandes();

        $reponse = $this->actingAs($this->camille)->get('/mes-demandes/recapitulatif/telecharger')->assertOk();

        $this->assertStringContainsString('attachment', $reponse->headers->get('Content-Disposition'));
        $this->assertStringContainsString('recapitulatif-demandes-nova-terra-2026-10-04.html', $reponse->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $reponse->headers->get('Cache-Control'));
        $this->verifierFichierAutonome($reponse->getContent());
        $this->assertStringContainsString('Toutes mes demandes', $reponse->getContent());
        $this->assertStringNotContainsString('Agent Secret', $reponse->getContent());
        $this->assertStringNotContainsString('Demande de Victor', $reponse->getContent());
    }

    // --- Version tableur (CSV) ---

    public function test_the_spreadsheet_version_has_the_readable_columns_and_still_neutralises_formulas(): void
    {
        $this->jeuDeDemandes();

        $reponse = $this->actingAs($this->camille)->get('/mes-demandes/export.csv')->assertOk();
        $csv = $reponse->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('no-store', $reponse->headers->get('Cache-Control'));

        $lignes = array_values(array_filter(preg_split('/\R/', substr($csv, 3))));
        $this->assertSame([
            'Référence', 'Objet', 'Service', 'Statut', 'Créée le', 'Dernière mise à jour', 'Traitée le',
            'Âge (jours)', 'Délai de traitement (jours)', 'Étape actuelle', 'Ce que cela signifie',
        ], str_getcsv($lignes[0], ';', '"', ''));
        $this->assertCount(6, $lignes); // en-tête + mes 5 demandes

        $lignes = array_map(fn ($l) => str_getcsv($l, ';', '"', ''), array_slice($lignes, 1));
        $parObjet = collect($lignes)->keyBy(1);

        $this->assertSame(['Traitée', '10/09/2026 10:00', '12/09/2026 10:00', '12/09/2026 10:00', '24', '2,0', 'Traitée', 'Le dossier est clos.'], array_slice($parObjet['Inscription bibliothèque'], 3));
        $this->assertSame('4,0', $parObjet['Carte de transport'][8]);
        $this->assertSame(['Nouvelle', '14', '', 'Enregistrée', 'Votre demande est enregistrée, aucun agent ne l\'a encore prise en charge.'],
            [$parObjet['Lampadaire en panne'][3], $parObjet['Lampadaire en panne'][7], $parObjet['Lampadaire en panne'][8], $parObjet['Lampadaire en panne'][9], $parObjet['Lampadaire en panne'][10]]);
        $this->assertSame(['En cours', 'Prise en charge', 'Un agent s\'en occupe.'], [$parObjet['Arrêt déplacé'][3], $parObjet['Arrêt déplacé'][9], $parObjet['Arrêt déplacé'][10]]);

        // Injection de formules : l'objet « =1+1 » est préfixé d'une apostrophe, jamais interprété par un tableur.
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringNotContainsString(';=1+1', $csv);

        foreach (['Agent Secret', 'Demande de Victor', 'Demande importée', 'Victor'] as $interdit) {
            $this->assertStringNotContainsString($interdit, $csv, $interdit);
        }
    }

    // --- Format informatique (JSON) et page « Mes données » ---

    public function test_the_json_stays_as_the_computer_format_with_a_french_description(): void
    {
        $this->jeuDeDemandes();

        $json = json_decode($this->actingAs($this->camille)->get('/mes-donnees/export.json')->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('description', array_key_first($json));
        $this->assertStringContainsString('Format informatique', $json['description']['objet']);
        $this->assertStringContainsString('Mon dossier', $json['description']['objet']);
        $this->assertCount(6, $json['description']['champs']);
        $this->assertCount(5, $json['demandes']);
        $this->assertStringNotContainsString('Agent Secret', json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    public function test_my_data_page_presents_the_two_documents_first_then_the_other_formats(): void
    {
        $page = $this->actingAs($this->camille)->get('/mes-donnees')->assertOk()
            ->assertSeeInOrder(['Vos deux documents', 'Mon dossier', 'Récapitulatif de mes demandes', 'Autres formats', 'Version tableur (CSV)', 'Format informatique (JSON)', 'Gérer mes données'])
            ->assertSee('réutiliser leurs données dans un autre outil')
            ->assertSee(route('mes-donnees.dossier'))->assertSee(route('mes-donnees.dossier.telecharger'))
            ->assertSee(route('demandes.recapitulatif'))->assertSee(route('demandes.recapitulatif.telecharger'))
            ->assertSee(route('demandes.export-csv'))->assertSee(route('mes-donnees.export'))
            ->assertSee(route('mes-donnees.suppression'))
            ->getContent();

        $this->assertSame(1, substr_count($page, '<h1'));
        $this->assertSame(1, substr_count($page, '<main'));
        $this->assertStringContainsString('Fil d&#039;Ariane', $page);
    }

    public function test_the_document_stylesheet_is_the_single_source_for_site_and_download(): void
    {
        $this->assertStringStartsWith("@import './document.css';", File::get(resource_path('css/app.css')));
        $this->assertFileExists(resource_path('css/document.css'));
        $this->assertDoesNotMatchRegularExpression('/\d\s*px\b/', File::get(resource_path('css/document.css'))); // rem, jamais de pixels
    }

    // --- Accès ---

    public function test_roles_and_guests_on_every_new_route(): void
    {
        $routes = ['/mes-donnees/dossier', '/mes-donnees/dossier/telecharger', '/mes-demandes/recapitulatif', '/mes-demandes/recapitulatif/telecharger', '/mes-demandes/export.csv'];

        foreach ([User::factory()->agent()->create(), User::factory()->admin()->create()] as $autre) {
            foreach ($routes as $url) {
                $this->actingAs($autre)->get($url)->assertForbidden();
            }
        }
        auth()->logout();
        foreach ($routes as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_downloads_share_the_ten_per_minute_limit(): void
    {
        $this->actingAs($this->camille);
        foreach (range(1, 10) as $i) {
            $this->get('/mes-donnees/dossier/telecharger')->assertOk();
        }
        $this->get('/mes-donnees/dossier/telecharger')->assertStatus(429);
        $this->get('/mes-demandes/recapitulatif/telecharger')->assertStatus(429);
    }

    // --- Requêtes ---

    public function test_the_documents_run_a_constant_number_of_queries(): void
    {
        $this->jeuDeDemandes();
        $urls = ['/mes-donnees/dossier', '/mes-donnees/dossier/telecharger', '/mes-demandes/recapitulatif', '/mes-demandes/recapitulatif/telecharger', '/mes-demandes/export.csv'];

        $compter = function (string $url): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->camille)->get($url)->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };
        $avant = array_map($compter, $urls);

        foreach (range(1, 20) as $i) {
            $this->demande($this->camille, "Demande {$i}", Statut::EnCours, '2026-09-30 10:00:00', null, [['en_cours', '2026-09-30 11:00:00', false]]);
        }

        $this->assertSame($avant, array_map(function ($url) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->camille)->get($url)->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        }, $urls));
    }
}
