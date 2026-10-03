<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DateLocale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Éco-conception (F57 à F60) : sobriété du code livré et page /eco-conception. */
class EcoConceptionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);

        parent::tearDown();
    }

    /** @return list<string> */
    private function fichiersSource(): array
    {
        $fichiers = [];
        foreach (['resources/views', 'resources/css', 'resources/js'] as $dossier) {
            foreach (File::allFiles(base_path($dossier)) as $f) {
                $fichiers[] = $f->getPathname();
            }
        }

        return $fichiers;
    }

    public function test_no_external_url_in_views_css_and_js(): void
    {
        $violations = [];
        foreach ($this->fichiersSource() as $fichier) {
            // Seul l'espace de noms XML des SVG intégrés est toléré : ce n'est pas une requête réseau.
            $texte = str_replace(['http://www.w3.org/2000/svg', 'http://www.w3.org/1999/xlink'], '', File::get($fichier));
            if (preg_match_all('~https?://[^\s"\')]+~i', $texte, $m)) {
                $violations[$fichier] = $m[0];
            }
        }

        $this->assertSame([], $violations, 'URL externe trouvée dans le code livré.');
    }

    public function test_no_third_party_font_and_system_font_stack(): void
    {
        $config = File::get(base_path('tailwind.config.js'));

        $this->assertStringNotContainsString('Figtree', $config);
        $this->assertStringNotContainsString('bunny', File::get(resource_path('views/layouts/app.blade.php')));
    }

    public function test_every_img_tag_has_alt_width_height_loading_and_decoding(): void
    {
        $manquants = [];
        foreach ($this->fichiersSource() as $fichier) {
            if (preg_match_all('/<img\b[^>]*>/i', File::get($fichier), $m)) {
                foreach ($m[0] as $balise) {
                    foreach (['alt=', 'width=', 'height=', 'loading=', 'decoding='] as $attribut) {
                        if (! str_contains($balise, $attribut)) {
                            $manquants[] = basename($fichier).' : '.$balise.' sans '.$attribut;
                        }
                    }
                }
            }
        }

        $this->assertSame([], $manquants);
    }

    public function test_no_video_audio_or_autoplay(): void
    {
        foreach ($this->fichiersSource() as $fichier) {
            $this->assertDoesNotMatchRegularExpression('/<(video|audio|iframe|embed|object)\b|autoplay/i', File::get($fichier), basename($fichier));
        }
    }

    public function test_every_script_of_public_pages_is_deferred(): void
    {
        foreach (['/', '/services', '/login', '/eco-conception'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            preg_match_all('/<script\b[^>]*>/i', $html, $m);

            foreach ($m[0] as $balise) {
                $this->assertMatchesRegularExpression('/type="module"|\bdefer\b/', $balise, $url.' : script bloquant '.$balise);
                $this->assertStringContainsString('src=', $balise, $url.' : pas de script en ligne');
            }
        }
    }

    public function test_layouts_have_no_script_without_defer(): void
    {
        foreach (['layouts/app', 'layouts/guest', 'welcome', 'components/tete-assets'] as $vue) {
            $source = File::get(resource_path("views/{$vue}.blade.php"));

            // Aucune balise script écrite à la main : le seul script vient de @vite (type="module", donc différé).
            $this->assertDoesNotMatchRegularExpression('/<script\b(?![^>]*(type="module"|\bdefer\b))/i', $source, $vue);
        
        }
    }

    public function test_save_data_header_removes_the_script_and_keeps_navigation_usable(): void
    {
        $normal = $this->get('/services')->assertOk()->assertHeader('Vary')->getContent();
        $this->assertStringContainsString('type="module"', $normal);
        $this->assertStringContainsString('<noscript>', $normal);

        $reponse = $this->withHeader('Save-Data', 'on')->get('/services')->assertOk();
        $economie = $reponse->getContent();

        $this->assertStringNotContainsString('<script', $economie);
        $this->assertStringNotContainsString('modulepreload', $economie);
        $this->assertStringContainsString('#menu-mobile', $economie); // règles qui montrent les menus sans script
        $this->assertStringContainsString('Save-Data', $reponse->headers->get('Vary'));
    }

    public function test_save_data_keeps_the_script_where_a_page_really_needs_it(): void
    {
        $this->actingAs(User::factory()->create())->withHeader('Save-Data', 'on')->get('/profile')->assertOk()
            ->assertSee('type="module"', false);
    }

    public function test_css_respects_reduced_motion(): void
    {
        $css = File::get(resource_path('css/app.css'));

        $this->assertStringContainsString('prefers-reduced-motion: reduce', $css);
    }

    public function test_authenticated_pages_are_private_for_caches(): void
    {
        foreach (['/espace', '/mes-demandes', '/profile'] as $url) {
            $cache = $this->actingAs(User::factory()->create())->get($url)->assertOk()->headers->get('Cache-Control');
            $this->assertStringContainsString('private', $cache, $url);
            $this->assertStringNotContainsString('public', $cache, $url);
        }
        $cache = $this->actingAs(User::factory()->agent()->create())->get('/agent')->assertOk()->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cache);
    }

    public function test_htaccess_adds_caching_and_compression_inside_ifmodule_blocks(): void
    {
        $htaccess = File::get(public_path('.htaccess'));

        $this->assertStringContainsString('immutable', $htaccess);
        $this->assertStringContainsString('/build/', $htaccess);
        $this->assertStringContainsString('<IfModule mod_deflate.c>', $htaccess);
        $this->assertStringContainsString('<IfModule mod_headers.c>', $htaccess);
        $sansCommentaires = preg_replace('/^\s*#.*$/m', '', $htaccess);
        $this->assertSame(substr_count($sansCommentaires, '<IfModule'), substr_count($sansCommentaires, '</IfModule>'));

        // Aucune directive de module hors d'un bloc <IfModule> : un module absent ne doit jamais provoquer de 500.
        $profondeur = 0;
        foreach (preg_split('/\R/', $htaccess) as $ligne) {
            $ligne = trim($ligne);
            if (str_starts_with($ligne, '</IfModule')) {
                $profondeur--;
            }
            if (preg_match('/^(Header|ExpiresActive|ExpiresByType|AddOutputFilterByType|BrowserMatch|RewriteEngine|RewriteRule|RewriteCond|Options)\b/', $ligne)) {
                $this->assertGreaterThan(0, $profondeur, 'Directive hors <IfModule> : '.$ligne);
            }
            if (str_starts_with($ligne, '<IfModule')) {
                $profondeur++;
            }
        }
    }

    public function test_the_build_produces_files_in_public_build(): void
    {
        $manifeste = public_path('build/manifest.json');
        $this->assertFileExists($manifeste, 'Lancez npm run build.');

        $entrees = json_decode(File::get($manifeste), true);
        foreach (['resources/css/app.css', 'resources/js/app.js'] as $entree) {
            $this->assertArrayHasKey($entree, $entrees);
            $this->assertGreaterThan(0, filesize(public_path('build/'.$entrees[$entree]['file'])));
        }
    }

    public function test_eco_page_is_public_and_shows_the_measured_figures(): void
    {
        $mesures = json_decode(File::get(resource_path('data/mesures-poids.json')), true);
        $this->assertArrayHasKey('avant', $mesures);
        $this->assertArrayHasKey('apres', $mesures);
        $this->assertArrayHasKey('apres_sans_regles', $mesures);

        $reponse = $this->get('/eco-conception')->assertOk()
            ->assertSee('Éco-conception de la plateforme')
            ->assertSee('<caption', false)
            ->assertSee('scope="col"', false)
            ->assertSee('Fil d&#039;Ariane', false);

        foreach (['avant', 'apres', 'apres_sans_regles'] as $etape) {
            $reponse->assertSee(DateLocale::format($mesures[$etape]['date']));
            $reponse->assertSee($mesures[$etape]['environnement']['navigateur']);

            foreach ($mesures[$etape]['pages'] as $page) {
                $reponse->assertSee(number_format($page['transfere_octets'] / 1024, 1, ',', ' ').' Ko');
                $reponse->assertSee($page['chemin']);
            }
        }

        $html = $reponse->getContent();
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertSame(1, substr_count($html, '<main'));
    }

    public function test_eco_page_is_linked_from_the_footer_for_everyone(): void
    {
        $url = route('eco-conception');

        $this->get('/')->assertSee($url);
        $this->actingAs(User::factory()->create())->get('/espace')->assertSee($url);
        $this->actingAs(User::factory()->agent()->create())->get('/agent')->assertSee($url);
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertSee($url);
    }

    public function test_public_pages_do_not_lazy_load_and_stay_light_on_sql(): void
    {
        Model::preventLazyLoading();

        foreach (['/', '/services', '/urgences', '/alertes', '/login', '/eco-conception', '/accessibilite'] as $url) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get($url)->assertOk();
            $requetes = count(DB::getQueryLog());
            DB::disableQueryLog();

            $this->assertLessThanOrEqual(4, $requetes, "{$url} : {$requetes} requêtes SQL");
        }
    }
}
