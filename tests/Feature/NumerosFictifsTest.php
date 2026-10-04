<?php

namespace Tests\Feature;

use App\Models\Service;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\NumerosFictifsSeeder;
use Database\Seeders\UrgenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Numéros de démonstration fictifs (plage 02 61 91 xx xx) : séparés de UrgenceSeeder, idempotents, sans écraser une saisie de l'admin. */
class NumerosFictifsTest extends TestCase
{
    use RefreshDatabase;

    public function test_urgence_seeder_still_writes_the_original_numbers_and_the_new_seeder_replaces_only_those(): void
    {
        $this->seed(UrgenceSeeder::class);
        $this->assertSame('0262 55 01 15', Service::where('slug', 'hopital-nova-terra')->value('telephone'));
        $this->assertSame('15', Service::where('slug', 'urgences-nova-terra')->value('telephone'));

        $this->seed(NumerosFictifsSeeder::class);

        $this->assertSame('02 61 91 55 01', Service::where('slug', 'hopital-nova-terra')->value('telephone'));
        $this->assertSame('02 61 91 55 15', Service::where('slug', 'urgences-nova-terra')->value('telephone'));
    }

    public function test_it_is_idempotent_and_never_overwrites_a_number_typed_by_the_admin(): void
    {
        $this->seed(UrgenceSeeder::class);
        Service::where('slug', 'urgences-nova-terra')->update(['telephone' => '02 61 91 00 77']);

        $this->seed(NumerosFictifsSeeder::class);
        $this->seed(NumerosFictifsSeeder::class);

        $this->assertSame('02 61 91 00 77', Service::where('slug', 'urgences-nova-terra')->value('telephone'));
        $this->assertSame('02 61 91 55 01', Service::where('slug', 'hopital-nova-terra')->value('telephone'));
    }

    public function test_it_does_nothing_without_the_services(): void
    {
        $this->seed(NumerosFictifsSeeder::class);

        $this->assertSame(0, Service::count());
    }

    public function test_a_fresh_install_ends_with_fictional_numbers_only(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (Service::whereNotNull('telephone')->pluck('telephone') as $numero) {
            $this->assertMatchesRegularExpression('/^02 61 91 \d{2} \d{2}$/', $numero, "$numero ressemble à un vrai numéro");
        }
        $this->assertSame(2, Service::whereNotNull('telephone')->count());
    }
}
