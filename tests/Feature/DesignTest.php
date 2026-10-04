<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Garde-fous du design Terra Nova : les couleurs viennent des jetons, jamais d'une valeur écrite dans une vue. */
class DesignTest extends TestCase
{
    public function test_every_token_of_the_design_is_a_css_variable_for_both_themes(): void
    {
        $tokens = json_decode(File::get(base_path('docs/design/ds/terra-nova/tokens.json')), true);
        $css = File::get(resource_path('css/terra-nova.css'));

        foreach ($tokens['color']['tokens'] as $jeton) {
            foreach (['jour', 'nuit'] as $theme) {
                $this->assertStringContainsString(
                    '--'.$jeton['name'].': '.$jeton['value'][$theme].';',
                    $css,
                    "Le jeton {$jeton['name']} ({$theme}) n'est pas à jour dans terra-nova.css."
                );
            }
        }
    }

    public function test_views_do_not_hard_code_colors(): void
    {
        foreach (File::allFiles(resource_path('views')) as $fichier) {
            // Le document téléchargeable est autonome (aucune feuille de style externe) : il garde ses propres couleurs.
            if (str_ends_with($fichier->getFilename(), 'document-autonome.blade.php')) {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression(
                '/(?<![\w&])#[0-9a-fA-F]{6}\b|(?<![\w&])#[0-9a-fA-F]{3}\b(?![\w-])(?=[;"\' )])/',
                File::get($fichier->getPathname()),
                $fichier->getRelativePathname().' : couleur écrite en dur (utiliser une variable ou une classe du design).'
            );
        }
    }

    public function test_the_design_fonts_are_hosted_locally(): void
    {
        $css = File::get(resource_path('css/terra-nova.css'));

        $this->assertDoesNotMatchRegularExpression('#url\(["\']?https?:#', $css);
        foreach (File::glob(resource_path('fonts/*.woff2')) as $police) {
            $this->assertStringContainsString(basename($police), $css, basename($police).' n\'est pas déclarée dans terra-nova.css.');
        }
    }
}
