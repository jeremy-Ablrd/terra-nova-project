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

/** F79 : filtre par service (sujet) dans /mes-demandes, avec libellé accessible et compteurs, combiné au statut et à la recherche. */
class FiltreServiceDemandesTest extends TestCase
{
    use RefreshDatabase;

    private User $camille;

    private Service $voirie;

    private Service $transports;

    protected function setUp(): void
    {
        parent::setUp();
        Model::preventLazyLoading();
        $this->camille = User::factory()->create();
        $this->voirie = Service::factory()->create(['nom' => 'Voirie et propreté', 'actif' => true]);
        $this->transports = Service::factory()->create(['nom' => 'Transports', 'actif' => true]);
    }

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);
        parent::tearDown();
    }

    private function demande(?Service $service, array $extra = []): Demande
    {
        return Demande::factory()->create(array_merge(['user_id' => $this->camille->id, 'service_id' => $service?->id], $extra));
    }

    private function page(string $query = ''): string
    {
        return $this->actingAs($this->camille)->get('/mes-demandes'.$query)->assertOk()->getContent();
    }

    /** @return list<string> références affichées dans le tableau */
    private function references(string $query = ''): array
    {
        preg_match_all('/NT-\d{4}-\d{5}/', strip_tags($this->page($query)), $m);

        return array_values(array_unique($m[0]));
    }

    public function test_the_filter_is_a_labelled_field_with_counters_and_works_without_javascript(): void
    {
        $this->demande($this->voirie);
        $this->demande($this->voirie);
        $this->demande($this->transports);
        $this->demande(null);

        $html = $this->page();

        $this->assertMatchesRegularExpression('/<label[^>]*for="filtre-service"[^>]*>\s*Service \(sujet de la demande\)\s*<\/label>/', $html);
        $this->assertStringContainsString('<form method="GET" action="'.route('demandes.index').'" aria-label="Filtrer par service"', $html);
        $this->assertStringContainsString('Tous les services (4)', $html);
        $this->assertStringContainsString('Transports (1)', $html);
        $this->assertStringContainsString('Voirie et propreté (2)', $html);
        $this->assertStringContainsString('À orienter (1)', $html);
        $this->assertMatchesRegularExpression('/<button[^>]*>\s*Filtrer\s*<\/button>/', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_choosing_a_service_keeps_only_its_requests_and_marks_the_option(): void
    {
        $v = $this->demande($this->voirie);
        $t = $this->demande($this->transports);
        $s = $this->demande(null);

        $this->assertSame([$v->reference], $this->references('?service='.$this->voirie->id));
        $this->assertSame([$t->reference], $this->references('?service='.$this->transports->id));
        $this->assertSame([$s->reference], $this->references('?service=aucun'));
        $this->assertMatchesRegularExpression('/<option value="'.$this->voirie->id.'" selected(="selected")?>Voirie et propreté \(1\)<\/option>/', $this->page('?service='.$this->voirie->id));
        $this->assertStringContainsString('Effacer le filtre de service', $this->page('?service='.$this->voirie->id));
    }

    public function test_an_unknown_foreign_or_malformed_service_is_ignored(): void
    {
        $this->demande($this->voirie);
        $autre = User::factory()->create();
        $service = Service::factory()->create(['nom' => 'Service du voisin', 'actif' => true]);
        Demande::factory()->create(['user_id' => $autre->id, 'service_id' => $service->id]);

        $this->assertCount(1, $this->references('?service=999999'));
        $this->assertCount(1, $this->references('?service='.$service->id));    // service d'un autre habitant : ignoré
        $this->assertCount(1, $this->references('?service[]=1'));
        $this->assertCount(1, $this->references('?service=abc'));
        $this->assertStringNotContainsString('Service du voisin', $this->page());
    }

    public function test_the_counters_follow_the_status_and_the_search_and_the_status_counters_follow_the_service(): void
    {
        $this->demande($this->voirie, ['statut' => Statut::Nouvelle, 'objet' => 'Lampadaire']);
        $this->demande($this->voirie, ['statut' => Statut::EnCours, 'objet' => 'Trottoir']);
        $this->demande($this->transports, ['statut' => Statut::EnCours, 'objet' => 'Arrêt de bus']);

        // Service : les nombres annoncés sont ceux qu'on obtient avec le statut choisi.
        $html = $this->page('?statut=en_cours');
        $this->assertStringContainsString('Voirie et propreté (1)', $html);
        $this->assertStringContainsString('Transports (1)', $html);
        $this->assertStringContainsString('Tous les services (2)', $html);

        // Recherche : « Trottoir » ne laisse que la voirie.
        $this->assertStringContainsString('Voirie et propreté (1)', $this->page('?q=Trottoir'));
        $this->assertStringContainsString('Transports (0)', $this->page('?q=Trottoir'));

        // Statut : les compteurs de statut tiennent compte du service choisi.
        $html = $this->page('?service='.$this->voirie->id);
        $this->assertMatchesRegularExpression('/Toutes\s*<span>\(2\)<\/span>/', $html);
        $this->assertMatchesRegularExpression('/Nouvelle\s*<span>\(1\)<\/span>/', $html);
        $this->assertMatchesRegularExpression('/En cours\s*<span>\(1\)<\/span>/', $html);
    }

    public function test_filters_combine_and_each_link_and_field_keeps_the_others(): void
    {
        $this->demande($this->voirie, ['statut' => Statut::EnCours, 'objet' => 'Trottoir cassé']);
        $this->demande($this->voirie, ['statut' => Statut::Nouvelle, 'objet' => 'Lampadaire']);
        $this->demande($this->transports, ['statut' => Statut::EnCours, 'objet' => 'Trottoir bus']);

        $this->assertCount(1, $this->references('?service='.$this->voirie->id.'&statut=en_cours&q=Trottoir'));

        $html = $this->page('?service='.$this->voirie->id.'&statut=en_cours&q=Trottoir');
        // Les liens de statut gardent le service et la recherche ; le champ de service garde le statut et la recherche.
        $this->assertStringContainsString(e(route('demandes.index', ['statut' => 'nouvelle', 'q' => 'Trottoir', 'service' => $this->voirie->id])), $html);
        $this->assertStringContainsString('<input type="hidden" name="statut" value="en_cours">', $html);
        $this->assertStringContainsString('<input type="hidden" name="q" value="Trottoir">', $html);
        $this->assertStringContainsString('<input type="hidden" name="service" value="'.$this->voirie->id.'">', $html);
    }

    public function test_pagination_keeps_the_service(): void
    {
        foreach (range(1, 12) as $i) {
            $this->demande($this->voirie);
        }
        $this->demande($this->transports);

        $page = $this->page('?service='.$this->voirie->id);

        $this->assertStringContainsString('service='.$this->voirie->id.'&amp;page=2', $page);
        $this->assertCount(2, $this->references('?service='.$this->voirie->id.'&page=2'));
    }

    public function test_an_empty_result_says_so_with_a_way_back(): void
    {
        $this->demande($this->voirie, ['statut' => Statut::Nouvelle]);
        $this->demande($this->transports, ['statut' => Statut::Traitee]);

        $this->actingAs($this->camille)->get('/mes-demandes?service='.$this->voirie->id.'&statut=traitee')->assertSee('Aucune demande avec ce statut.');
        $this->assertSame([], $this->references('?service='.$this->voirie->id.'&statut=traitee'));
    }

    public function test_without_any_request_there_is_no_service_filter(): void
    {
        $this->actingAs($this->camille)->get('/mes-demandes')->assertOk()->assertDontSee('Filtrer par service');
    }

    public function test_only_my_requests_are_listed_or_counted(): void
    {
        $this->demande($this->voirie);
        $autre = User::factory()->create();
        Demande::factory()->count(3)->create(['user_id' => $autre->id, 'service_id' => $this->voirie->id]);
        Demande::factory()->importee()->create(['service_id' => $this->voirie->id]);

        $html = $this->page('?service='.$this->voirie->id);

        $this->assertStringContainsString('Voirie et propreté (1)', $html);
        $this->assertCount(1, $this->references('?service='.$this->voirie->id));
    }

    public function test_the_page_uses_a_constant_number_of_queries_and_no_lazy_loading(): void
    {
        $mesure = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->camille)->get('/mes-demandes?service='.$this->voirie->id)->assertOk();

            return count(DB::getQueryLog());
        };

        $this->demande($this->voirie);
        $this->demande($this->transports);
        $peu = $mesure();

        foreach (range(1, 8) as $i) {
            $this->demande(Service::factory()->create(['actif' => true]));
            $this->demande($this->voirie);
        }

        $this->assertSame($peu, $mesure());
    }
}
