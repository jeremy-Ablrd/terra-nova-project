<?php

namespace Tests\Feature;

use App\Models\Demande;
use App\Models\User;
use App\Support\DateLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FuseauHoraireTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_timezone_is_indian_reunion(): void
    {
        $this->assertSame('Indian/Reunion', config('app.timezone'));
        $this->assertSame('Indian/Reunion', date_default_timezone_get());
        $this->assertSame('+04:00', now()->format('P'));
    }

    public function test_date_locale_uses_a_single_format_in_local_time(): void
    {
        // 06:00 UTC = 10:00 à La Réunion.
        $this->assertSame('03/10/2026 10:00', DateLocale::format(Carbon::parse('2026-10-03 06:00:00', 'UTC')));
        $this->assertSame('03/10/2026 10:00', DateLocale::format(Carbon::parse('2026-10-03T06:00:00+00:00')));
        $this->assertSame('03/10/2026 10:00', DateLocale::format('2026-10-03T06:00:00+00:00'));
        // Passage de minuit : 21:30 UTC = 01:30 le lendemain.
        $this->assertSame('04/01/2027 01:30', DateLocale::format(Carbon::parse('2027-01-03 21:30:00', 'UTC')));
        $this->assertSame('', DateLocale::format(null));
    }

    public function test_demande_created_at_a_precise_instant_is_displayed_at_the_right_local_time(): void
    {
        $citoyen = User::factory()->create();

        // 2026-10-03 06:00 UTC = 10:00 heure locale.
        $this->travelTo(Carbon::parse('2026-10-03 06:00:00', 'UTC'));
        $demande = Demande::factory()->for($citoyen)->create();
        $this->travelBack();

        $this->assertSame('10:00', $demande->fresh()->created_at->format('H:i'));

        $this->actingAs($citoyen)->get(route('contact.confirmation', $demande))
            ->assertOk()
            ->assertSee('03/10/2026 10:00');

        $this->actingAs($citoyen)->get(route('demandes.show', $demande))->assertSee('03/10/2026 10:00');
        $this->actingAs($citoyen)->get('/mes-demandes')->assertSee('03/10/2026 10:00');

        $this->actingAs(User::factory()->agent()->create())->get('/agent/demandes')
            ->assertSee('title="03/10/2026 10:00"', false);
    }

    public function test_account_creation_date_is_displayed_in_local_time(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 21:30:00', 'UTC')); // 01:30 le 04/10 à La Réunion
        $user = User::factory()->create();
        $this->travelBack();

        $this->actingAs($user)->get('/espace')->assertSee('04/10/2026 01:30');
    }

    public function test_views_never_print_raw_or_custom_formatted_dates(): void
    {
        $forbidden = '/translatedFormat\(|->format\(|toDateTimeString|toDateString|\{\{\s*[^}]*(created_at|updated_at|traitee_at|first_seen_at|email_verified_at)\s*\}\}/';

        foreach (File::allFiles(resource_path('views')) as $file) {
            $this->assertDoesNotMatchRegularExpression(
                $forbidden,
                File::get($file->getPathname()),
                "{$file->getRelativePathname()} affiche une date sans passer par DateLocale::format()."
            );
        }
    }
}
