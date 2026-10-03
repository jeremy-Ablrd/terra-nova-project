<?php

namespace Tests\Feature;

use App\Enums\ActionJournal;
use App\Enums\CategorieService;
use App\Enums\Disponibilite;
use App\Models\Demande;
use App\Models\JournalActivite;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/** F63 (désactiver rapidement un service défectueux) et F64 (état d'un service avant une démarche). */
class ServicesEtatTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $agent;

    private User $citoyen;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-04 12:00:00');
        Model::preventLazyLoading();

        $this->admin = User::factory()->admin()->create();
        $this->agent = User::factory()->agent()->create();
        $this->citoyen = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Model::preventLazyLoading(false);

        parent::tearDown();
    }

    private function desactiver(Service $service, array $donnees = ['motif_interruption' => 'Panne du guichet', 'alternative' => 'Passez à l\'accueil'], ?User $qui = null)
    {
        return $this->actingAs($qui ?? $this->admin)->post(route('admin.services.desactiver', $service), $donnees);
    }

    // --- F63 : la coupure d'urgence côté admin ---

    public function test_one_click_deactivation_updates_the_service_the_journal_and_the_public_list(): void
    {
        $service = Service::factory()->create(['nom' => 'Guichet en ligne']);

        $this->desactiver($service)->assertRedirect(route('admin.services.index'))->assertSessionHas('success');

        $service->refresh();
        $this->assertSame(Disponibilite::Desactive, $service->disponibilite);
        $this->assertSame('Panne du guichet', $service->motif_interruption);
        $this->assertSame('Passez à l\'accueil', $service->alternative);
        $this->assertSame('2026-10-04 12:00:00', $service->desactive_at->format('Y-m-d H:i:s'));

        $entree = JournalActivite::firstOrFail();
        $this->assertSame(ActionJournal::ServiceModifie, $entree->action);
        $this->assertSame($this->admin->id, $entree->acteur_id);
        $this->assertSame('Guichet en ligne', $entree->objet_libelle);
        $this->assertStringContainsString('disponibilité : Disponible → Service désactivé', $entree->detail);
        foreach (['Panne du guichet', 'Passez', $this->admin->email, $this->citoyen->email] as $interdit) {
            $this->assertStringNotContainsString($interdit, $entree->detail.$entree->objet_libelle, $interdit); // pas de contenu libre ni de donnée personnelle
        }

        $this->get('/services')->assertSee('Service désactivé')->assertSee('Pas de demande possible pour ce service pour le moment');
    }

    public function test_the_reason_is_mandatory_and_nothing_changes_without_it(): void
    {
        $service = Service::factory()->create();

        foreach ([[], ['motif_interruption' => ''], ['motif_interruption' => '   '], ['alternative' => 'Une alternative seule'], ['motif_interruption' => str_repeat('a', 501)]] as $donnees) {
            $this->desactiver($service, $donnees)->assertSessionHasErrors('motif_interruption');
        }
        $this->desactiver($service, [])->assertSessionHasErrors(['motif_interruption' => 'Indiquez le motif de la désactivation.']);

        $this->assertSame(Disponibilite::Disponible, $service->fresh()->disponibilite);
        $this->assertSame(0, JournalActivite::count());
    }

    public function test_the_alternative_is_optional(): void
    {
        $service = Service::factory()->create();

        $this->desactiver($service, ['motif_interruption' => 'Incident technique'])->assertSessionHasNoErrors();

        $this->assertNull($service->fresh()->alternative);
        $this->assertTrue($service->fresh()->estDesactive());
    }

    public function test_reactivation_clears_reason_and_alternative_and_is_journaled(): void
    {
        $service = Service::factory()->interrompu()->create();
        $this->desactiver($service);

        $this->actingAs($this->admin)->post(route('admin.services.reactiver', $service))->assertRedirect(route('admin.services.index'))->assertSessionHas('success');

        $service->refresh();
        $this->assertSame(Disponibilite::Disponible, $service->disponibilite);
        $this->assertNull($service->motif_interruption);
        $this->assertNull($service->alternative);
        $this->assertNull($service->retour_estime_at);
        $this->assertNull($service->desactive_at);
        $this->assertSame(2, JournalActivite::count());
        $this->assertStringContainsString('disponibilité : Service désactivé → Disponible', JournalActivite::latest('id')->first()->detail);
    }

    public function test_if_the_journal_fails_the_deactivation_is_rolled_back(): void
    {
        $service = Service::factory()->create();
        JournalActivite::creating(fn () => throw new RuntimeException('journal indisponible'));
        $this->withoutExceptionHandling();

        try {
            $this->desactiver($service);
            $this->fail('Une exception était attendue.');
        } catch (RuntimeException) {
        }

        $this->assertSame(Disponibilite::Disponible, $service->fresh()->disponibilite);
        $this->assertNull($service->fresh()->motif_interruption);
        $this->assertNull($service->fresh()->desactive_at);
    }

    public function test_the_admin_list_has_the_buttons_without_javascript(): void
    {
        $actif = Service::factory()->create(['nom' => 'Service sain']);
        $coupe = Service::factory()->desactive()->create(['nom' => 'Service coupé']);

        $page = $this->actingAs($this->admin)->get('/admin/services')->assertOk()
            ->assertSee('Coupure d&#039;urgence', false)
            ->assertSee(route('admin.services.desactiver', $actif))
            ->assertSee(route('admin.services.reactiver', $coupe))
            ->assertDontSee(route('admin.services.reactiver', $actif))
            ->assertDontSee(route('admin.services.desactiver', $coupe))
            ->assertSee('Motif (obligatoire)')
            ->getContent();

        $this->assertStringContainsString('<details>', $page);                 // ouverture native, aucun script
        $this->assertMatchesRegularExpression('/name="motif_interruption" type="text" required/', $page);
        $this->assertStringNotContainsString('x-data', explode('<tbody', $page)[1]);
        $this->assertStringContainsString('Service désactivé', $page);
    }

    public function test_only_the_admin_can_deactivate_or_reactivate(): void
    {
        $service = Service::factory()->create();
        $coupe = Service::factory()->desactive()->create();

        foreach ([$this->agent, $this->citoyen] as $autre) {
            $this->desactiver($service, qui: $autre)->assertForbidden();
            $this->actingAs($autre)->post(route('admin.services.reactiver', $coupe))->assertForbidden();
        }
        $this->assertSame(Disponibilite::Disponible, $service->fresh()->disponibilite);
        $this->assertTrue($coupe->fresh()->estDesactive());

        auth()->logout();
        $this->post(route('admin.services.desactiver', $service), ['motif_interruption' => 'x'])->assertRedirect(route('login'));
        $this->post(route('admin.services.reactiver', $coupe))->assertRedirect(route('login'));
        $this->assertSame(0, JournalActivite::count());
    }

    public function test_the_full_edit_form_also_knows_the_disabled_state(): void
    {
        $service = Service::factory()->create();
        $base = ['prioritaire' => '0'];

        $this->actingAs($this->admin)->get(route('admin.services.edit', $service))->assertSee('Service désactivé');

        $this->actingAs($this->admin)->put(route('admin.services.update', $service), $base + ['disponibilite' => 'desactive'])
            ->assertSessionHasErrors(['motif_interruption' => 'Indiquez le motif de la désactivation.']);
        $this->assertSame(Disponibilite::Disponible, $service->fresh()->disponibilite);

        $this->actingAs($this->admin)->put(route('admin.services.update', $service), $base + ['disponibilite' => 'desactive', 'motif_interruption' => 'Coupure'])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($service->fresh()->desactive_at);

        $this->actingAs($this->admin)->put(route('admin.services.update', $service), $base + ['disponibilite' => 'interrompu', 'motif_interruption' => 'Maintenance'])
            ->assertSessionHasNoErrors();
        $this->assertNull($service->fresh()->desactive_at);
        $this->assertTrue($service->fresh()->estInterrompu());
    }

    // --- F63 : côté habitant, /contact ---

    public function test_contact_disables_the_option_of_a_disabled_service_and_keeps_the_others(): void
    {
        $coupe = Service::factory()->desactive()->create(['nom' => 'Service coupé']);
        $sain = Service::factory()->create(['nom' => 'Service sain']);
        $interrompu = Service::factory()->interrompu()->create(['nom' => 'Service en pause']);

        $page = $this->actingAs($this->citoyen)->get('/contact')->assertOk()
            ->assertSee('Service coupé — service désactivé')
            ->assertSee('Services actuellement désactivés')
            ->assertSee('Panne du guichet en ligne.')
            ->getContent();

        $this->assertMatchesRegularExpression('/<option value="'.$coupe->id.'" disabled>Service coupé/', $page);
        $this->assertMatchesRegularExpression('/<option value="'.$sain->id.'" >Service sain/', $page);
        $this->assertMatchesRegularExpression('/<option value="'.$interrompu->id.'" >Service en pause — service interrompu/', $page);
        $this->assertStringContainsString('Je ne sais pas', $page);
        $this->assertStringContainsString('aria-describedby="services_interrompus services_desactives"', $page);

        // Même avec ?service_id= : jamais présélectionné.
        $this->actingAs($this->citoyen)->get('/contact?service_id='.$coupe->id)->assertDontSee('selected="selected"', false);
    }

    public function test_the_server_refuses_a_disabled_service_even_if_the_form_was_modified(): void
    {
        $coupe = Service::factory()->desactive()->create(['nom' => 'Service coupé']);
        $donnees = ['objet' => 'Une demande', 'message' => 'Un message assez long pour passer.'];

        $this->actingAs($this->citoyen)->post('/contact', $donnees + ['service_id' => $coupe->id])
            ->assertSessionHasErrors(['service_id' => 'Le service « Service coupé » est désactivé pour le moment : choisissez un autre service ou « Je ne sais pas ».']);
        $this->assertSame(0, Demande::count());
    }

    public function test_the_form_stays_usable_for_other_services_interrupted_ones_and_to_be_oriented(): void
    {
        Service::factory()->desactive()->create();
        $sain = Service::factory()->create();
        $interrompu = Service::factory()->interrompu()->create();
        $donnees = ['objet' => 'Une demande', 'message' => 'Un message assez long pour passer.'];

        foreach ([$sain->id, $interrompu->id, null] as $serviceId) {
            $this->actingAs($this->citoyen)->post('/contact', $donnees + ['service_id' => $serviceId])->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertSame(3, Demande::count());
    }

    public function test_reactivation_makes_the_service_selectable_again(): void
    {
        $service = Service::factory()->create(['nom' => 'Guichet']);
        $donnees = ['objet' => 'Une demande', 'message' => 'Un message assez long pour passer.', 'service_id' => $service->id];

        $this->desactiver($service);
        $this->actingAs($this->citoyen)->post('/contact', $donnees)->assertSessionHasErrors('service_id');

        $this->actingAs($this->admin)->post(route('admin.services.reactiver', $service));
        $this->actingAs($this->citoyen)->get('/contact')->assertDontSee('Guichet — service désactivé');
        $this->actingAs($this->citoyen)->post('/contact', $donnees)->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_a_demande_already_linked_to_a_disabled_service_stays_readable(): void
    {
        $service = Service::factory()->create(['nom' => 'Guichet lié']);
        $demande = Demande::factory()->create(['user_id' => $this->citoyen->id, 'service_id' => $service->id, 'objet' => 'Ma demande liée'])->refresh();

        $this->desactiver($service);

        $this->actingAs($this->citoyen)->get('/mes-demandes')->assertOk()->assertSee('Guichet lié')->assertSee('Ma demande liée');
        $this->actingAs($this->citoyen)->get(route('demandes.show', $demande))->assertOk()->assertSee('Ma demande liée');
        $this->actingAs($this->agent)->get(route('agent.demandes.show', $demande))->assertOk()->assertSee('Guichet lié');
    }

    // --- F64 : synthèse, filtre et tri ---

    private function catalogue(int $disponibles, int $interrompus, int $desactives): void
    {
        Service::factory()->count($disponibles)->create();
        Service::factory()->count($interrompus)->interrompu()->create();
        Service::factory()->count($desactives)->desactive()->create();
    }

    public function test_the_synthesis_and_the_counters_are_exact(): void
    {
        $this->catalogue(6, 1, 1);

        $this->get('/services')->assertOk()
            ->assertSee('6 services disponibles, 1 interrompu, 1 désactivé.')
            ->assertSeeInOrder(['Tous les états', '(8)', 'Disponible', '(6)', 'Service interrompu', '(1)', 'Service désactivé', '(1)']);
    }

    public function test_the_synthesis_has_no_absurd_sentence_and_agrees_in_number(): void
    {
        $this->get('/services')->assertSee('Aucun service n\'est renseigné pour le moment.');

        $this->catalogue(1, 0, 0);
        $this->get('/services')->assertSee('Le service est disponible.')->assertDontSee('désactivé.')->assertDontSee('0 désactivé');

        Service::query()->delete();
        $this->catalogue(6, 0, 0);
        $this->get('/services')->assertSee('Les 6 services sont tous disponibles.')->assertDontSee('0 interrompu')->assertDontSee('0 désactivé');

        Service::query()->delete();
        $this->catalogue(6, 1, 0); // zéro désactivé : pas de « 0 désactivé »
        $this->get('/services')->assertSee('6 services disponibles, 1 interrompu.')->assertDontSee('0 désactivé');

        Service::query()->delete();
        $this->catalogue(1, 2, 2);
        $this->get('/services')->assertSee('1 service disponible, 2 interrompus, 2 désactivés.');

        Service::query()->delete();
        $this->catalogue(0, 0, 1);
        $this->get('/services')->assertSee('Aucun service disponible, 1 désactivé.');
    }

    public function test_the_state_filter_with_counters_and_an_unknown_value(): void
    {
        $this->catalogue(2, 1, 1);

        $page = $this->get('/services?etat=desactive')->assertOk()->getContent();
        $this->assertSame(1, substr_count($page, '<article'));
        $this->assertSame(2, substr_count($page, 'aria-current="true"')); // un lien actif par série de filtres (état, catégorie)
        $this->assertMatchesRegularExpression('/class="[^"]*filtre-actif[^"]*"\s+aria-current="true"\s*>\s*Service désactivé/', $page);
        $this->assertStringContainsString('Service désactivé', $page);

        foreach (['?etat=nimporte', '?etat[]=desactive', '?etat='] as $requete) {
            $this->assertSame(4, substr_count($this->get('/services'.$requete)->assertOk()->getContent(), '<article'));
        }
    }

    public function test_the_two_filters_combine_and_each_keeps_the_other(): void
    {
        Service::factory()->categorie(CategorieService::Sante)->desactive()->create();
        Service::factory()->categorie(CategorieService::Sante)->create();
        Service::factory()->categorie(CategorieService::Transport)->desactive()->create();

        $page = $this->get('/services?categorie=sante&etat=desactive')->assertOk()->getContent();

        $this->assertSame(1, substr_count($page, '<article'));
        $this->assertStringContainsString('categorie=sante&amp;etat=desactive', $page);
        $this->assertMatchesRegularExpression('/Service désactivé <span>\(1\)<\/span>/', $page); // compteur d'état : seulement la santé
    }

    public function test_unavailable_services_rise_after_the_priority_ones(): void
    {
        Service::factory()->create(['nom' => 'Service D disponible', 'ordre' => 1]);
        Service::factory()->interrompu()->create(['nom' => 'Service C interrompu', 'ordre' => 2]);
        Service::factory()->desactive()->create(['nom' => 'Service B désactivé', 'ordre' => 3]);
        Service::factory()->prioritaire()->create(['nom' => 'Service A prioritaire disponible', 'ordre' => 4]);

        $this->get('/services')->assertSeeInOrder(['Service A prioritaire disponible', 'Service B désactivé', 'Service C interrompu', 'Service D disponible']);
    }

    // --- Prochaine action possible : liste, fiche, /urgences ---

    private function troisServices(): array
    {
        $communs = ['categorie' => CategorieService::Sante, 'urgence' => true];

        return [
            Service::factory()->create($communs + ['nom' => 'Urgences ouvertes']),
            Service::factory()->interrompu()->create($communs + ['nom' => 'Urgences en pause', 'alternative' => 'Allez à l\'hôpital voisin']),
            Service::factory()->desactive()->create($communs + ['nom' => 'Urgences coupées', 'alternative' => 'Appelez le standard']),
        ];
    }

    public function test_the_next_action_is_written_for_each_state_on_the_list_the_sheet_and_urgences(): void
    {
        [$ouvert, $pause, $coupe] = $this->troisServices();
        $contactSansService = route('contact.create');
        $this->actingAs($this->citoyen);

        foreach (['/services', '/urgences'] as $url) {
            $page = $this->get($url)->assertOk()
                ->assertSee('Prochaine action possible')
                ->assertSee('Faire une demande à ce service')
                ->assertSee('Vous pouvez quand même faire une demande')
                ->assertSee('Pas de demande possible pour ce service pour le moment')
                ->assertSee('Contacter la mairie sans choisir de service')
                ->assertSee('href="'.$contactSansService.'"', false)
                ->assertSee('href="'.route('contact.create', ['service_id' => $ouvert->id]).'"', false)
                ->assertSee('Voir les autres services de la catégorie « Santé »')
                ->assertSee('Appelez le standard')
                ->getContent();
            $this->assertStringNotContainsString('href="'.route('contact.create', ['service_id' => $coupe->id]).'"', $page, $url);
        }

        $fiche = fn (Service $s) => $this->get(route('services.show', $s))->assertOk();
        $fiche($ouvert)->assertSee('Faire une demande à ce service');
        $fiche($pause)->assertSee('Vous pouvez quand même faire une demande')->assertSee('Allez à l&#039;hôpital voisin', false);
        $fiche($coupe)->assertSee('Pas de demande possible pour ce service pour le moment')->assertSee('Contacter la mairie sans choisir de service')
            ->assertDontSee('Faire une demande à ce service');
    }

    public function test_the_sheet_of_an_unavailable_service_puts_state_reason_and_action_first_in_a_status_region(): void
    {
        [, $pause, $coupe] = $this->troisServices();

        foreach ([$coupe, $pause] as $service) {
            $page = $this->actingAs($this->citoyen)->get(route('services.show', $service))->getContent();

            $positionEncart = strpos($page, 'role="status"');
            $this->assertNotFalse($positionEncart);
            $this->assertLessThan(strpos($page, 'Description'), $positionEncart);
            $this->assertLessThan(strpos($page, 'Retour aux services'), $positionEncart); // avant tout bouton
            $this->assertSame(1, substr_count($page, 'role="status"'));
            $this->assertStringContainsString($service->estDesactive() ? 'Service désactivé' : 'Service interrompu', substr($page, $positionEncart, 1500));
            $this->assertStringContainsString('Motif', substr($page, $positionEncart, 1500));
            $this->assertStringContainsString('Prochaine action possible', substr($page, $positionEncart, 2500));
        }

        // Un service disponible n'a pas d'encart d'état.
        $this->assertSame(0, substr_count($this->get(route('services.show', Service::factory()->create()))->getContent(), 'role="status"'));
    }

    public function test_guests_get_the_login_link_and_agents_no_request_link(): void
    {
        [$ouvert, , $coupe] = $this->troisServices();

        $this->get(route('services.show', $coupe))->assertSee('Se connecter pour contacter la mairie')->assertDontSee('Contacter la mairie sans choisir de service');
        $this->get('/services')->assertSee('Se connecter pour faire une demande');

        $this->actingAs($this->agent)->get('/services')->assertOk()
            ->assertDontSee(route('contact.create'))
            ->assertDontSee('Contacter la mairie sans choisir de service')
            ->assertSee('Pas de demande possible pour ce service pour le moment');
    }

    // --- Tableau de bord agent ---

    public function test_the_agent_dashboard_counts_disabled_services_read_only(): void
    {
        $this->actingAs($this->agent)->get('/agent')->assertSee('Aucun service désactivé')->assertSee('Aucun service interrompu');

        $this->catalogue(1, 1, 2);
        Service::factory()->desactive()->create(['actif' => false]); // masqué du public : ne compte pas

        $page = $this->actingAs($this->agent)->get('/agent')->assertOk()
            ->assertSee('2 services désactivés')
            ->assertSee('1 service interrompu')
            ->getContent();
        $this->assertStringNotContainsString(route('admin.services.index'), $page); // lecture seule : aucun lien vers la gestion
    }

    // --- Requêtes ---

    public function test_pages_do_not_lazy_load_and_run_a_constant_number_of_queries(): void
    {
        $this->troisServices();
        $this->catalogue(2, 1, 1);
        $urls = ['/services', '/services?etat=desactive', '/urgences', '/contact'];

        $compter = function (string $url): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->citoyen)->get($url)->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };
        $avant = array_map($compter, $urls);

        $this->catalogue(15, 5, 5);

        $this->assertSame($avant, array_map($compter, $urls));
    }
}
