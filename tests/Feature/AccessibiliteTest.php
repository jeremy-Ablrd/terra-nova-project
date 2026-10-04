<?php

namespace Tests\Feature;

use App\Enums\TailleTexte;
use App\Enums\ThemeAffichage;
use App\Models\Demande;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AccessibiliteTest extends TestCase
{
    use RefreshDatabase;

    // --- Aides -----------------------------------------------------------------------------------------

    private function css(): string
    {
        return File::get(resource_path('css/app.css'));
    }

    /** @return array<string, string> variables CSS déclarées dans le premier bloc dont le sélecteur est $selecteur */
    /** Variables de couleur d'un bloc ; les alias `var(--autre)` sont résolus comme le ferait le navigateur (sur le même élément). */
    private function variables(string $selecteur): array
    {
        $lire = function (string $sel): array {
            preg_match('/'.preg_quote($sel, '/').'s*{([^}]*)}/', $this->css(), $bloc);
            preg_match_all('/(--[a-z-]+)s*:s*(#[0-9a-fA-F]{6}|var((--[a-z-]+)))s*;/', $bloc[1] ?? '', $paires, PREG_SET_ORDER);

            return collect($paires)->mapWithKeys(fn ($p) => [$p[1] => strtolower($p[2])])->all();
        };

        $propres = $lire($selecteur);
        $toutes = $selecteur === ':root' ? $propres : array_merge($lire(':root'), $propres);
        $resoudre = function (string $valeur) use (&$resoudre, $toutes) {
            return str_starts_with($valeur, 'var(') ? $resoudre($toutes[trim(substr($valeur, 4), ')')] ?? '#000000') : $valeur;
        };

        return collect($propres)->map(fn ($v) => $resoudre($v))->all();
    }

    private function luminance(string $hex): float
    {
        $canaux = array_map(fn ($v) => hexdec($v) / 255, str_split(ltrim($hex, '#'), 2));
        $canaux = array_map(fn ($v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $canaux);

        return 0.2126 * $canaux[0] + 0.7152 * $canaux[1] + 0.0722 * $canaux[2];
    }

    private function ratio(string $a, string $b): float
    {
        [$x, $y] = [$this->luminance($a), $this->luminance($b)];

        return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
    }

    private function assertContrast(float $minimum, string $a, string $b, string $nom): void
    {
        $this->assertGreaterThanOrEqual($minimum, $this->ratio($a, $b), "$nom : $a sur $b = ".round($this->ratio($a, $b), 2).":1 (< $minimum:1)");
    }

    /** @return array<string, array{0: ?User, 1: string}> pages passant par le layout commun ou le layout invité */
    private function pages(): array
    {
        $citoyen = User::factory()->create();
        $demande = Demande::factory()->for($citoyen)->create();

        return [
            'accueil' => [null, '/'],
            'services' => [null, '/services'],
            'urgences' => [null, '/urgences'],
            'alertes' => [null, '/alertes'],
            'accessibilité' => [null, '/accessibilite'],
            'connexion' => [null, '/login'],
            'inscription' => [null, '/register'],
            'espace citoyen' => [$citoyen, '/espace'],
            'mes demandes' => [$citoyen, '/mes-demandes'],
            'détail demande' => [$citoyen, '/mes-demandes/'.$demande->id],
            'contact' => [$citoyen, '/contact'],
            'profil' => [$citoyen, '/profile'],
            'espace agent' => [User::factory()->agent()->create(), '/agent'],
            'centre technique' => [User::factory()->agent()->create(), '/agent/demandes'],
            'admin' => [User::factory()->admin()->create(), '/admin'],
            'admin comptes' => [User::factory()->admin()->create(), '/admin/comptes'],
            'admin services' => [User::factory()->admin()->create(), '/admin/services'],
            'admin alertes' => [User::factory()->admin()->create(), '/admin/alertes'],
            'admin synchronisation' => [User::factory()->admin()->create(), '/admin/synchronisation'],
        ];
    }

    private function html(?User $user, string $url): string
    {
        return ($user ? $this->actingAs($user) : $this)->get($url)->assertOk()->getContent();
    }

    // --- Taille du texte et thème : classe racine, cookie, compte ----------------------------------------

    public function test_default_display_has_no_size_or_theme_class(): void
    {
        $this->get('/')->assertSee('<html lang="fr" class="">', false);
    }

    public function test_text_size_control_sets_a_cookie_and_the_root_class_changes(): void
    {
        $this->post(route('preferences.affichage'), ['taille' => 'grand'])->assertRedirect()->assertCookie('affichage_taille', 'grand');

        $this->withCookie('affichage_taille', 'grand')->get('/')->assertSee('<html lang="fr" class="taille-grand">', false);
        $this->withCookie('affichage_taille', 'tres_grand')->get('/')->assertSee('<html lang="fr" class="taille-tres-grand">', false);
        $this->withCookie('affichage_taille', 'normal')->get('/')->assertSee('<html lang="fr" class="">', false);
    }

    public function test_theme_control_sets_a_cookie_and_the_root_class_changes(): void
    {
        $this->post(route('preferences.affichage'), ['theme' => 'contraste'])->assertRedirect()->assertCookie('affichage_theme', 'contraste');

        $this->withCookie('affichage_theme', 'contraste')->get('/')->assertSee('<html lang="fr" class="theme-contraste">', false);
        $this->withCookie('affichage_theme', 'standard')->get('/')->assertSee('<html lang="fr" class="">', false);
    }

    public function test_size_and_theme_combine_and_apply_on_every_layout(): void
    {
        $cookies = ['affichage_taille' => 'tres_grand', 'affichage_theme' => 'contraste'];

        foreach (['/', '/login', '/services', '/accessibilite'] as $url) { // accueil, layout invité, layout commun
            $this->withCookies($cookies)->get($url)->assertSee('<html lang="fr" class="taille-tres-grand theme-contraste">', false);
        }
    }

    public function test_a_single_control_changes_only_its_own_setting(): void
    {
        $this->withCookie('affichage_taille', 'grand')->post(route('preferences.affichage'), ['theme' => 'contraste'])
            ->assertCookie('affichage_theme', 'contraste')
            ->assertCookieMissing('affichage_taille'); // le cookie de taille n'est pas réécrit : il reste tel quel côté navigateur
    }

    public function test_unknown_values_are_rejected_and_unknown_cookies_are_ignored(): void
    {
        $this->post(route('preferences.affichage'), ['taille' => 'gigantesque'])->assertSessionHasErrors('taille')->assertCookieMissing('affichage_taille');
        $this->post(route('preferences.affichage'), ['theme' => 'neon'])->assertSessionHasErrors('theme')->assertCookieMissing('affichage_theme');

        $this->withCookies(['affichage_taille' => 'énorme', 'affichage_theme' => '<script>'])->get('/')
            ->assertSee('<html lang="fr" class="">', false);
    }

    public function test_the_choice_is_saved_on_the_account_when_logged_in(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('preferences.affichage'), ['taille' => 'grand'])
            ->assertRedirect()->assertCookie('affichage_taille', 'grand');
        $this->assertSame(['taille' => 'grand'], $user->fresh()->preferences);

        // Les autres préférences du compte sont conservées.
        $this->actingAs($user->fresh())->post(route('preferences.affichage'), ['theme' => 'contraste']);
        $this->assertSame(['taille' => 'grand', 'theme' => 'contraste'], $user->fresh()->preferences);

        // Sans aucun cookie (autre appareil) : le compte suffit.
        $this->actingAs($user->fresh())->get('/espace')->assertSee('<html lang="fr" class="taille-grand theme-contraste">', false);
    }

    public function test_the_account_takes_precedence_over_the_cookie_and_other_visitors_are_not_affected(): void
    {
        $user = User::factory()->create(['preferences' => ['taille' => 'tres_grand']]);

        $this->actingAs($user)->withCookie('affichage_taille', 'normal')->get('/espace')
            ->assertSee('<html lang="fr" class="taille-tres-grand">', false);

        auth()->logout();
        $this->get('/')->assertSee('<html lang="fr" class="">', false);
    }

    public function test_a_logged_in_user_without_account_preferences_uses_the_cookie(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withCookie('affichage_taille', 'grand')->get('/espace')
            ->assertSee('<html lang="fr" class="taille-grand">', false);
    }

    public function test_preferences_cannot_be_mass_assigned_through_the_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', ['name' => 'Camille', 'email' => $user->email, 'preferences' => ['taille' => 'grand']]);

        $this->assertNull($user->fresh()->preferences);
    }

    public function test_after_a_choice_the_user_returns_to_the_same_page_and_never_to_another_site(): void
    {
        $this->from('/services')->post(route('preferences.affichage'), ['taille' => 'grand'])->assertRedirect(url('/services'));
        $this->from('https://pirate.example/piege')->post(route('preferences.affichage'), ['taille' => 'grand'])->assertRedirect(url('/'));
    }

    // --- Les contrôles : accessibles, sans JavaScript --------------------------------------------------------

    public function test_the_controls_have_accessible_names_and_expose_their_state(): void
    {
        $html = $this->withCookies(['affichage_taille' => 'grand', 'affichage_theme' => 'contraste'])->get('/services')->getContent();

        $this->assertStringContainsString('aria-label="Réglages d&#039;affichage"', $html);

        // Chaque groupe est nommé par un texte visible qui existe dans la page.
        foreach (['affichage-taille' => 'Taille du texte', 'affichage-theme' => 'Thème'] as $id => $texte) {
            $this->assertStringContainsString('role="group" aria-labelledby="'.$id.'"', $html);
            $this->assertMatchesRegularExpression('/<span id="'.$id.'"[^>]*>\s*'.preg_quote($texte, '/').'/u', $html);
        }

        // Chaque bouton a un texte, et un seul bouton par groupe est « enfoncé ».
        preg_match_all('/<button type="submit" name="(taille|theme)" value="([a-z_]+)"\s+aria-pressed="(true|false)"[^>]*>([^<]+)<\/button>/u', $html, $boutons, PREG_SET_ORDER);
        $this->assertCount(count(TailleTexte::cases()) + count(ThemeAffichage::cases()), $boutons);
        foreach ($boutons as $b) {
            $this->assertNotSame('', trim($b[4]), 'Un bouton de réglage n\'a pas de texte.');
        }
        $enfonces = collect($boutons)->where(2, '!==', null)->filter(fn ($b) => $b[3] === 'true')->map(fn ($b) => $b[1].'='.$b[2])->sort()->values()->all();
        $this->assertSame(['taille=grand', 'theme=contraste'], $enfonces);

        // Texte visible de l'état courant : trait et coché en CSS, classe sur le bouton actif.
        $this->assertSame(2, substr_count($html, 'bouton-reglage-actif'));
    }

    public function test_the_controls_work_without_javascript(): void
    {
        $html = $this->get('/')->getContent();
        preg_match('/<form method="POST" action="[^"]*preferences\/affichage"[^>]*>(.*?)<\/form>/s', $html, $formulaire);

        $this->assertNotEmpty($formulaire, 'Le formulaire de réglages est absent.');
        $this->assertStringContainsString('name="_token"', $formulaire[1]);
        $this->assertDoesNotMatchRegularExpression('/(x-on:|@click|onclick|x-data)/', $formulaire[0]);
        $this->assertSame(5, substr_count($formulaire[1], '<button type="submit"'));
    }

    // --- Lien d'évitement, repères, un seul main et un seul h1 -------------------------------------------------

    public function test_the_skip_link_is_the_first_focusable_element_on_every_page(): void
    {
        foreach ($this->pages() as $nom => [$user, $url]) {
            $html = preg_replace('/<head>.*?<\/head>/s', '', $this->html($user, $url));
            preg_match_all('/<(a|button|input|select|textarea|summary)\b([^>]*)>/i', $html, $balises, PREG_SET_ORDER);

            $premiere = collect($balises)->first(fn ($b) => ! (strtolower($b[1]) === 'a' && ! str_contains($b[2], 'href'))
                && ! (strtolower($b[1]) === 'input' && str_contains($b[2], 'type="hidden"')));

            $this->assertNotNull($premiere, "$nom : aucun élément focusable.");
            $this->assertSame('a', strtolower($premiere[1]), "$nom : le premier focusable n'est pas un lien.");
            $this->assertStringContainsString('href="#contenu"', $premiere[2], "$nom : le premier focusable n'est pas le lien d'évitement.");
            $this->assertStringContainsString('skip-link', $premiere[2]);
            $this->assertMatchesRegularExpression('/<a href="#contenu"[^>]*>\s*Aller au contenu\s*<\/a>/u', $html, "$nom : texte du lien d'évitement.");
        }
    }

    public function test_every_page_has_a_single_main_that_the_skip_link_targets(): void
    {
        foreach ($this->pages() as $nom => [$user, $url]) {
            $html = $this->html($user, $url);

            $this->assertSame(1, preg_match_all('/<main\b/', $html), "$nom : il faut exactement un <main>.");
            $this->assertMatchesRegularExpression('/<main id="contenu" tabindex="-1"/', $html, "$nom : <main> doit être la cible du lien d'évitement.");
            $this->assertSame(1, substr_count($html, 'id="contenu"'), "$nom : id=\"contenu\" doit être unique.");
        }
    }

    public function test_every_page_has_a_single_h1_except_the_sign_in_forms_left_to_the_final_audit(): void
    {
        foreach ($this->pages() as $nom => [$user, $url]) {
            if (in_array($nom, ['connexion', 'inscription'], true)) {
                continue; // pages d'authentification : titre à ajouter lors de l'audit des formulaires (F21 + F42 + D20)
            }

            $this->assertSame(1, preg_match_all('/<h1\b/', $this->html($user, $url)), "$nom : il faut exactement un <h1>.");
        }
    }

    public function test_landmarks_header_nav_main_and_footer_are_present_and_not_duplicated(): void
    {
        foreach ($this->pages() as $nom => [$user, $url]) {
            if ($nom === 'profil') {
                continue; // les sections du profil (formulaires) utilisent leur propre <header> : audit final
            }

            $html = $this->html($user, $url);

            $this->assertSame(1, preg_match_all('/<header\b/', $html), "$nom : un seul <header>.");
            $this->assertSame(1, preg_match_all('/<footer\b/', $html), "$nom : un seul <footer>.");
            if (! in_array($nom, ['connexion', 'inscription'], true)) {
                $this->assertGreaterThanOrEqual(1, preg_match_all('/<nav\b/', $html), "$nom : une navigation.");
            }
        }
    }

    public function test_the_footer_links_to_the_accessibility_page_on_every_page(): void
    {
        foreach ($this->pages() as $nom => [$user, $url]) {
            $this->assertMatchesRegularExpression(
                '/<footer\b.*<a href="'.preg_quote(route('accessibilite'), '/').'"[^>]*>\s*Accessibilité\s*<\/a>.*<\/footer>/su',
                $this->html($user, $url),
                "$nom : lien « Accessibilité » absent du pied de page."
            );
        }
    }

    // --- Page /accessibilite ---------------------------------------------------------------------------

    public function test_the_accessibility_page_is_public_and_describes_what_is_available(): void
    {
        foreach ([null, User::factory()->create(), User::factory()->agent()->create(), User::factory()->admin()->create()] as $user) {
            $this->html($user, '/accessibilite');
        }

        $this->get('/accessibilite')->assertOk()
            ->assertSee('<title>Accessibilité – ', false)
            ->assertSee('aria-label="Fil d&#039;Ariane"', false)
            ->assertSee('Taille du texte')
            ->assertSee('très grand (150 %)')
            ->assertSee('Contraste et couleurs')
            ->assertSee('Contraste renforcé')
            ->assertSee('Navigation au clavier')
            ->assertSee('Aller au contenu')
            ->assertSee('Échap')
            ->assertSee('Aucune information n&#039;est donnée par la couleur seule', false)
            ->assertSee('Réglages d&#039;affichage', false);
    }

    // --- Navigation : menu mobile au clavier, liens, états ---------------------------------------------------

    public function test_the_mobile_menu_and_account_menu_are_keyboard_operable(): void
    {
        $html = $this->html(User::factory()->create(), '/espace');

        // Bouton du menu : un vrai <button>, nommé, qui annonce son état et ce qu'il contrôle ; Échap le ferme.
        $this->assertMatchesRegularExpression('/<button type="button" x-ref="burger"[^>]*aria-controls="menu-mobile"[^>]*aria-label="Menu"/s', $html);
        $this->assertStringContainsString('x-bind:aria-expanded="open"', $html);
        $this->assertStringContainsString('id="menu-mobile"', $html);
        $this->assertStringContainsString('@keydown.escape.window="if (open) { open = false; $refs.burger.focus() }"', $html);

        // Menu du compte : bouton avec aria-haspopup / aria-expanded, fermé par Échap.
        $this->assertStringContainsString('aria-haspopup="true" x-bind:aria-expanded="open"', $html);
        $this->assertStringContainsString('@keydown.escape.window="open = false"', $html);

        // Le logo n'est pas le seul contenu d'un lien sans nom.
        $this->assertStringContainsString('aria-label="Terra Nova — accueil"', $html);
    }

    public function test_the_current_page_link_is_announced_and_not_only_coloured(): void
    {
        $html = $this->html(User::factory()->create(), '/services');

        $this->assertMatchesRegularExpression('/<a [^>]*href="[^"]*\/services"[^>]*aria-current="page"/', $html);
        $this->assertMatchesRegularExpression('/class="[^"]*nav-lien[^"]*"[^>]*aria-current="page"/', $html);
        // Deux liens (bureau + menu mobile) ; le fil d'Ariane ajoute son propre <span aria-current="page">.
        $this->assertSame(2, preg_match_all('/<a [^>]*aria-current="page"/', $html));
    }

    public function test_navigation_does_not_use_fixed_heights_that_clip_text(): void
    {
        foreach (['layouts/navigation', 'layouts/invite', 'layouts/app', 'layouts/guest', 'welcome'] as $vue) {
            $source = File::get(resource_path("views/$vue.blade.php"));

            $this->assertDoesNotMatchRegularExpression('/\bh-16\b/', $source, "$vue : hauteur fixe h-16 (utiliser min-h).");
            $this->assertStringNotContainsString('sm:flex', $vue === 'layouts/navigation' ? $source : '', 'Le menu bascule en mode mobile avant lg.');
        }
    }

    // --- CSS : rem, focus, cibles, contrastes ----------------------------------------------------------------

    public function test_the_stylesheet_has_no_pixel_values(): void
    {
        $this->assertDoesNotMatchRegularExpression('/[0-9.]\s*px\b/i', $this->css(), 'app.css contient une valeur en px : utiliser rem.');
    }

    public function test_views_have_no_pixel_sizes(): void
    {
        foreach (File::allFiles(resource_path('views')) as $fichier) {
            $source = File::get($fichier->getPathname());

            $this->assertDoesNotMatchRegularExpression('/\[[0-9.]+px\]/', $source, $fichier->getRelativePathname().' : valeur arbitraire en px (ex. text-[14px]).');
            $this->assertDoesNotMatchRegularExpression('/style="[^"]*[0-9]px/', $source, $fichier->getRelativePathname().' : style en px.');
        }
    }

    public function test_text_size_classes_change_the_root_size_in_percent(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression('/html\s*\{\s*font-size:\s*100%;/', $css);
        $this->assertMatchesRegularExpression('/html\.taille-grand\s*\{\s*font-size:\s*125%;/', $css);
        $this->assertMatchesRegularExpression('/html\.taille-tres-grand\s*\{\s*font-size:\s*150%;/', $css);
        $this->assertStringContainsString('html.theme-contraste', $css);
    }

    public function test_focus_is_always_visible_and_never_removed_without_replacement(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression('/:focus-visible\s*\{[^}]*outline:\s*var\(--epaisseur-focus\)\s*solid\s*var\(--couleur-focus\)\s*!important/', $css);
        $this->assertDoesNotMatchRegularExpression('/outline:\s*(none|0)\b/', $css);
        $this->assertMatchesRegularExpression('/\.skip-link\s*\{[^}]*position:\s*absolute/', $css);
        $this->assertMatchesRegularExpression('/\.skip-link:focus\s*\{\s*top:\s*0\.5rem/', $css);
        $this->assertMatchesRegularExpression('/html\.theme-contraste\s*\{[^}]*--epaisseur-focus:\s*0\.3125rem/', $css);
    }

    public function test_targets_are_at_least_one_and_a_half_rem(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression('/nav a,\s*footer a\s*\{[^}]*min-height:\s*1\.5rem/', $css);
        $this->assertMatchesRegularExpression('/button,\s*\[type="submit"\]\s*\{\s*min-height:\s*1\.5rem/', $css);
        $this->assertMatchesRegularExpression('/input\[type="checkbox"\],\s*input\[type="radio"\]\s*\{[^}]*width:\s*1\.5rem;\s*height:\s*1\.5rem/', $css);
    }

    public function test_standard_theme_colors_meet_wcag_contrast(): void
    {
        $v = $this->variables(':root');
        $blanc = '#fffdf9';      // surface-raised : cartes et champs
        $fondPage = '#ebe3d8';   // surface-sunken (bg-gray-100) : barre de réglages, bandeaux
        $gris50 = '#f6f1ea';     // surface (bg-gray-50) : fond des pages

        // Texte : 4,5:1 minimum sur tous les fonds.
        foreach ([$blanc, $gris50, $fondPage] as $fond) {
            $this->assertContrast(4.5, $v['--texte'], $fond, 'texte');
            $this->assertContrast(4.5, $v['--texte-discret'], $fond, 'texte discret');
        }
        $this->assertContrast(4.5, $v['--succes-texte'], $blanc, 'texte de succès');
        $this->assertContrast(4.5, $v['--succes-texte'], $v['--success-bg'], 'texte de succès sur fond de succès');
        $this->assertContrast(4.5, $v['--bouton-desactive-texte'], $v['--bouton-desactive-bg'], 'bouton désactivé (texte)');

        // Composants d'interface : 3:1 minimum.
        foreach ([$blanc, $gris50] as $fond) {
            $this->assertContrast(3, $v['--bordure-champ'], $fond, 'bordure de champ');
        }
        foreach ([$blanc, $gris50, $fondPage] as $fond) {
            $this->assertContrast(3, $v['--couleur-focus'], $fond, 'contour de focus');
            $this->assertContrast(3, $v['--lien-actif-bord'], $fond, 'bordure du lien actif');
            $this->assertContrast(3, $v['--filtre-actif-bord'], $fond, 'filtre actif');
        }
        $this->assertContrast(3, $v['--bouton-desactive-bg'], $blanc, 'bouton désactivé (bord contre la page)');

        // Mises en avant : la bordure contraste avec son propre fond.
        foreach (['demande-nouvelle', 'demande-en-cours', 'alerte-urgent', 'alerte-vigilance', 'alerte-info', 'service-interrompu'] as $nom) {
            $this->assertContrast(3, $v["--$nom-bord"], $v["--$nom-bg"], $nom);
            $this->assertContrast(4.5, $v['--texte'], $v["--$nom-bg"], "texte sur fond $nom");
        }
    }

    public function test_high_contrast_theme_colors_are_at_least_aaa_and_complete(): void
    {
        $standard = $this->variables(':root');
        $fort = $this->variables('html.theme-contraste');
        $blanc = '#ffffff';

        // Chaque variable de couleur du thème standard a sa version renforcée (rien n'est oublié).
        foreach (array_keys($standard) as $nom) {
            $this->assertArrayHasKey($nom, $fort, "$nom n'est pas redéfinie dans le thème « Contraste renforcé ».");
        }

        foreach (['--texte', '--texte-discret', '--succes-texte', '--bordure-champ', '--couleur-focus', '--lien-actif-bord', '--filtre-actif-bord'] as $nom) {
            $this->assertContrast(7, $fort[$nom], $blanc, "contraste renforcé $nom");
        }
        $this->assertContrast(7, $fort['--bouton-desactive-texte'], $fort['--bouton-desactive-bg'], 'bouton désactivé renforcé');

        foreach (['demande-nouvelle', 'demande-en-cours', 'alerte-urgent', 'alerte-vigilance', 'alerte-info', 'service-interrompu'] as $mise) {
            $this->assertContrast(7, $fort["--$mise-bord"], $fort["--$mise-bg"], "contraste renforcé $mise");
        }

        // Les classes grises, les bordures claires, les liens et les pastilles sont ramenés au noir et blanc.
        $css = $this->css();
        foreach (['.theme-contraste .bg-gray-100', '.theme-contraste .text-gray-500', '.theme-contraste .border-gray-300', '.theme-contraste a:not(.skip-link)', '.theme-contraste [class*="bg-indigo-100"]'] as $regle) {
            $this->assertStringContainsString($regle, $css, "Règle absente du thème renforcé : $regle");
        }
        $this->assertMatchesRegularExpression('/\.theme-contraste a:not\(\.skip-link\)\s*\{[^}]*text-decoration:\s*underline/', $css);
    }

    public function test_status_level_and_active_states_keep_a_text_in_addition_to_colour(): void
    {
        // Statut : le badge écrit toujours le statut ; niveau d'alerte : le bandeau écrit toujours le niveau.
        $demande = Demande::factory()->create();
        $agent = User::factory()->agent()->create();

        $this->actingAs($agent)->get('/agent/demandes')->assertSee('Nouvelle');
        $this->assertStringContainsString('font-weight: 700', $this->css()); // filtre actif : gras
        $this->assertMatchesRegularExpression('/\.filtre-actif\s*\{[^}]*text-decoration:\s*underline/', $this->css());
        $this->assertNotNull($demande);
    }
}
