<?php

namespace Tests\Feature;

use App\Enums\Statut;
use App\Models\Demande;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AgentDemandesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);

        parent::tearDown();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/agent/demandes')->assertRedirect('/login');
    }

    public function test_citizen_and_admin_get_403(): void
    {
        Demande::factory()->create();

        $this->actingAs(User::factory()->create())->get('/agent/demandes')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/agent/demandes')->assertForbidden();
    }

    public function test_agent_sees_demandes_of_several_citizens(): void
    {
        $service = Service::factory()->create(['nom' => 'Voirie test']);
        $camille = User::factory()->create(['name' => 'Camille Dupont']);
        $louis = User::factory()->create(['name' => 'Louis Martin']);
        $a = Demande::factory()->for($camille)->create(['objet' => 'Lampadaire en panne', 'service_id' => $service->id]);
        $b = Demande::factory()->for($louis)->create(['objet' => 'Question diverse']);

        $this->actingAs(User::factory()->agent()->create())->get('/agent/demandes')
            ->assertOk()
            ->assertSee('Centre technique municipal')
            ->assertSee($a->fresh()->reference)
            ->assertSee($b->fresh()->reference)
            ->assertSee('Lampadaire en panne')
            ->assertSee('Question diverse')
            ->assertSee('Camille Dupont')
            ->assertSee('Louis Martin')
            ->assertSee('Voirie test')
            ->assertSee('À orienter');
    }

    public function test_table_is_accessible_and_dates_have_a_full_date_title(): void
    {
        Demande::factory()->create(['created_at' => now()->subDays(2)]);

        $this->actingAs(User::factory()->agent()->create())->get('/agent/demandes')
            ->assertSee('<caption', false)
            ->assertSee('scope="col"', false)
            ->assertSee('<time datetime="', false)
            ->assertSee('title="'.now()->subDays(2)->format('d/m/Y H:i').'"', false)
            ->assertSee('il y a 2 jours');
    }

    public function test_page_is_named_centre_technique_municipal_in_h1_and_title(): void
    {
        $response = $this->actingAs(User::factory()->agent()->create())->get('/agent/demandes')->assertOk();

        $response->assertSee('>Centre technique municipal</h1>', false);
        $response->assertSee('<title>Centre technique municipal – '.config('app.name').'</title>', false);
    }

    public function test_citizen_and_admin_do_not_see_the_centre_technique_municipal_label_in_navigation(): void
    {
        $this->actingAs(User::factory()->create())->get('/espace')
            ->assertOk()
            ->assertDontSee('Centre technique municipal');

        $this->actingAs(User::factory()->admin()->create())->get('/admin')
            ->assertOk()
            ->assertDontSee('Centre technique municipal');
    }

    public function test_breadcrumb_is_shown_with_links(): void
    {
        $this->actingAs(User::factory()->agent()->create())->get('/agent/demandes')
            ->assertSee('aria-label="Fil d&#039;Ariane"', false)
            ->assertSee('Accueil')
            ->assertSee('href="'.route('agent.index').'"', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_new_and_in_progress_rows_are_highlighted_by_class_and_text(): void
    {
        Demande::factory()->statut(Statut::Nouvelle)->create();
        Demande::factory()->statut(Statut::EnCours)->create();
        Demande::factory()->statut(Statut::Traitee)->create();

        $response = $this->actingAs(User::factory()->agent()->create())->get('/agent/demandes')
            ->assertSee('class="demande-nouvelle"', false)
            ->assertSee('class="demande-en-cours"', false)
            ->assertSee('À prendre en charge')
            ->assertSee('En cours de traitement');

        // Statuts toujours écrits en toutes lettres (badge), jamais la couleur seule.
        $response->assertSee('Nouvelle')->assertSee('En cours')->assertSee('Traitée');
        $this->assertSame(1, substr_count($response->getContent(), 'class="demande-nouvelle"'));
        $this->assertSame(1, substr_count($response->getContent(), 'class="demande-en-cours"'));
    }

    public function test_empty_state_is_shown_in_french(): void
    {
        $this->actingAs(User::factory()->agent()->create())->get('/agent/demandes')
            ->assertOk()
            ->assertSee('Aucune demande pour le moment.');
    }

    public function test_list_is_paginated_by_fifteen(): void
    {
        Demande::factory()->count(16)->create();
        $agent = User::factory()->agent()->create();

        $page1 = $this->actingAs($agent)->get('/agent/demandes');
        $page1->assertOk()->assertSee('?page=2', false)->assertSee('Suivant');
        $this->assertSame(15, substr_count($page1->getContent(), '<time datetime='));

        $page2 = $this->actingAs($agent)->get('/agent/demandes?page=2');
        $page2->assertOk();
        $this->assertSame(1, substr_count($page2->getContent(), '<time datetime='));
    }

    public function test_no_n_plus_one_queries(): void
    {
        $agent = User::factory()->agent()->create();
        Model::preventLazyLoading(); // toute relation chargée à la demande lève une exception

        $count = function () use ($agent): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($agent)->get('/agent/demandes')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        Service::factory()->count(3)->create();
        Demande::factory()->count(2)->create(['service_id' => Service::first()->id]);
        $few = $count();

        Demande::factory()->count(13)->create(['service_id' => Service::latest('id')->first()->id]);
        $many = $count();

        $this->assertSame($few, $many, 'Le nombre de requêtes ne doit pas dépendre du nombre de demandes.');
    }

    public function test_only_the_agent_sees_the_demandes_link_in_navigation(): void
    {
        $url = route('agent.demandes.index');

        $this->actingAs(User::factory()->agent()->create())->get('/agent')->assertSee($url);
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertDontSee($url);
        $this->actingAs(User::factory()->create())->get('/espace')->assertDontSee($url);
    }
}
