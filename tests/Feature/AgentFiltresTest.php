<?php

namespace Tests\Feature;

use App\Enums\Statut;
use App\Models\Demande;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AgentFiltresTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->agent()->create();
    }

    /** 2 nouvelles, 1 en cours, 3 traitées, avec des objets reconnaissables. */
    private function seedMix(): void
    {
        foreach ([[Statut::Nouvelle, 2], [Statut::EnCours, 1], [Statut::Traitee, 3]] as [$statut, $n]) {
            for ($i = 1; $i <= $n; $i++) {
                Demande::factory()->statut($statut)->create(['objet' => "Objet {$statut->value} $i"]);
            }
        }
    }

    public function test_each_status_filter_only_lists_that_status(): void
    {
        $this->seedMix();

        foreach (Statut::cases() as $statut) {
            $response = $this->actingAs($this->agent)->get('/agent/demandes?statut='.$statut->value)->assertOk();

            foreach (Statut::cases() as $autre) {
                $autre === $statut
                    ? $response->assertSee("Objet {$autre->value} 1")
                    : $response->assertDontSee("Objet {$autre->value} ");
            }
        }
    }

    public function test_unknown_status_is_ignored_and_returns_the_full_list(): void
    {
        $this->seedMix();

        foreach (['?statut=nimporte', '?statut=', '?statut[]=nouvelle', '?statut[a]=b'] as $query) {
            $this->actingAs($this->agent)->get('/agent/demandes'.$query)
                ->assertOk()
                ->assertSee('Objet nouvelle 1')
                ->assertSee('Objet en_cours 1')
                ->assertSee('Objet traitee 1');
        }
    }

    public function test_pagination_keeps_the_filter(): void
    {
        Demande::factory()->count(16)->statut(Statut::Nouvelle)->create();
        Demande::factory()->statut(Statut::Traitee)->create(['objet' => 'Hors filtre']);

        $page1 = $this->actingAs($this->agent)->get('/agent/demandes?statut=nouvelle')->assertOk();
        $page1->assertSee('statut=nouvelle&amp;page=2', false)->assertDontSee('Hors filtre');
        $this->assertSame(15, substr_count($page1->getContent(), '<time datetime='));

        $page2 = $this->actingAs($this->agent)->get('/agent/demandes?statut=nouvelle&page=2')->assertOk();
        $this->assertSame(1, substr_count($page2->getContent(), '<time datetime='));
        $page2->assertDontSee('Hors filtre');
    }

    public function test_counters_are_exact_and_independent_of_the_active_filter(): void
    {
        $this->seedMix();

        foreach (['', '?statut=traitee', '?statut=nimporte'] as $query) {
            $this->actingAs($this->agent)->get('/agent/demandes'.$query)
                ->assertSeeInOrder(['Toutes', '(6)', 'Nouvelle', '(2)', 'En cours', '(1)', 'Traitée', '(3)']);
        }

        Demande::factory()->statut(Statut::Nouvelle)->create();
        $this->actingAs($this->agent)->get('/agent/demandes')
            ->assertSeeInOrder(['Toutes', '(7)', 'Nouvelle', '(3)', 'En cours', '(1)', 'Traitée', '(3)']);
    }

    public function test_active_filter_link_has_aria_current_and_a_non_color_style(): void
    {
        $this->seedMix();

        // Deux filtres (statut, priorité), chacun dans son propre repère de navigation au nom accessible distinct : un seul lien actif par filtre.
        $groupe = function (string $html, string $nom): string {
            $this->assertSame(1, preg_match('~<nav aria-label="'.preg_quote($nom, '~').'">(.*?)</nav>~s', $html, $m), "repère « {$nom} » introuvable ou en double");

            return $m[1];
        };

        $all = $this->actingAs($this->agent)->get('/agent/demandes')->getContent();
        $this->assertNotSame('Filtrer par statut', 'Filtrer par priorité'); // noms accessibles différents
        $this->assertSame(1, substr_count($groupe($all, 'Filtrer par statut'), 'aria-current="true"'));
        $this->assertSame(1, substr_count($groupe($all, 'Filtrer par priorité'), 'aria-current="true"'));
        $this->assertMatchesRegularExpression('/class="[^"]*filtre-actif[^"]*"\s+aria-current="true"\s*>\s*Toutes/', $all);

        $filtered = $this->actingAs($this->agent)->get('/agent/demandes?statut=en_cours')->getContent();
        $this->assertSame(1, substr_count($groupe($filtered, 'Filtrer par statut'), 'aria-current="true"'));
        $this->assertSame(1, substr_count($groupe($filtered, 'Filtrer par priorité'), 'aria-current="true"'));
        $this->assertMatchesRegularExpression('/class="[^"]*filtre-actif[^"]*"\s+aria-current="true"\s*>\s*En cours/', $filtered);
    }

    public function test_filter_with_no_result_shows_a_clean_empty_state(): void
    {
        Demande::factory()->statut(Statut::Nouvelle)->create();

        $this->actingAs($this->agent)->get('/agent/demandes?statut=traitee')
            ->assertOk()
            ->assertSee('Aucune demande avec ce statut.')
            ->assertSee('Voir toutes les demandes')
            ->assertSee('href="'.route('agent.demandes.index').'"', false);
    }

    public function test_counters_use_one_grouped_query_whatever_the_number_of_demandes(): void
    {
        $count = function (): array {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->agent)->get('/agent/demandes')->assertOk();
            DB::disableQueryLog();
            $queries = collect(DB::getQueryLog())->pluck('query');

            return [$queries->count(), $queries->filter(fn ($q) => str_contains($q, 'group by'))->count()];
        };

        Demande::factory()->count(3)->create();
        [$fewTotal, $fewGrouped] = $count();

        foreach ([Statut::Nouvelle, Statut::EnCours, Statut::Traitee] as $statut) {
            Demande::factory()->count(12)->statut($statut)->create();
        }
        [$manyTotal, $manyGrouped] = $count();

        $this->assertSame(1, $fewGrouped);
        $this->assertSame(1, $manyGrouped, 'Les compteurs doivent rester une seule requête groupée.');
        $this->assertSame($fewTotal, $manyTotal);
    }

    public function test_nav_counter_is_visible_for_the_agent_with_singular_and_plural(): void
    {
        $this->actingAs($this->agent)->get('/agent/demandes')->assertSee('Aucune demande en attente');

        Demande::factory()->statut(Statut::Nouvelle)->create();
        Demande::factory()->statut(Statut::EnCours)->create(); // n'est pas « en attente »
        $this->actingAs($this->agent)->get('/agent/demandes')
            ->assertSee('1 demande en attente')
            ->assertDontSee('1 demandes en attente');

        Demande::factory()->count(2)->statut(Statut::Nouvelle)->create();
        $this->actingAs($this->agent)->get('/agent/demandes')->assertSee('3 demandes en attente');
        $this->actingAs($this->agent)->get('/agent')->assertSee('3 demandes en attente');
    }

    public function test_agent_home_links_to_pending_and_all_demandes(): void
    {
        Demande::factory()->statut(Statut::Nouvelle)->create();

        $this->actingAs($this->agent)->get('/agent')
            ->assertOk()
            ->assertSee('Charge de travail')
            ->assertSee('Centre technique municipal : demandes en attente')
            ->assertSee('statut=nouvelle', false)
            ->assertSee('Centre technique municipal : toutes les demandes');
    }

    public function test_nav_counter_is_absent_for_citizen_and_admin_and_runs_no_query_for_them(): void
    {
        Demande::factory()->statut(Statut::Nouvelle)->create();
        $pendingQuery = fn ($q) => str_contains($q, 'count(*)') && str_contains($q, '"statut"');

        foreach ([[User::factory()->create(), '/espace'], [User::factory()->admin()->create(), '/admin']] as [$user, $url]) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($user)->get($url)->assertOk()->assertDontSee('en attente');
            DB::disableQueryLog();

            $this->assertCount(0, collect(DB::getQueryLog())->pluck('query')->filter($pendingQuery));
        }

        // Invité : page de connexion, aucune requête sur les demandes.
        auth()->logout();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/login')->assertOk()->assertDontSee('en attente');
        DB::disableQueryLog();
        $this->assertCount(0, collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => str_contains($q, 'demandes')));

        // Contrôle : pour l'agent, la requête de la nav est bien exécutée.
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->agent)->get('/agent/demandes')->assertOk();
        DB::disableQueryLog();
        $this->assertGreaterThanOrEqual(1, collect(DB::getQueryLog())->pluck('query')->filter($pendingQuery)->count());
    }

    public function test_filters_do_not_open_access_to_others(): void
    {
        $this->actingAs(User::factory()->create())->get('/agent/demandes?statut=nouvelle')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/agent/demandes?statut=nouvelle')->assertForbidden();
    }
}
