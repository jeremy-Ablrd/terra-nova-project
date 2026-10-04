<?php

namespace Tests\Feature;

use App\Enums\Priorite;
use App\Models\Demande;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** F86 : case « urgence médicale » du formulaire de contact, consigne avant l'envoi et sur la confirmation. */
class UrgenceMedicaleTest extends TestCase
{
    use RefreshDatabase;

    private User $citoyen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->citoyen = User::factory()->create();
        Service::factory()->urgence()->create(['nom' => 'Urgences de test', 'actif' => true, 'telephone' => '02 61 91 55 15']);
    }

    private function donnees(array $surcharge = []): array
    {
        return array_merge(['objet' => 'Malaise d\'un voisin', 'message' => 'Mon voisin a fait un malaise dans la rue.'], $surcharge);
    }

    public function test_the_form_warns_before_sending_that_the_platform_is_not_a_real_time_emergency_service(): void
    {
        $this->actingAs($this->citoyen)->get(route('contact.create'))->assertOk()
            ->assertSeeInOrder([
                'Cette plateforme n\'est pas un service d\'urgence en temps réel.',
                'appelez directement les secours',
                'Urgences de test',
                '02 61 91 55 15',
                'Il s\'agit d\'une urgence médicale',
                'Envoyer ma demande',
            ])
            ->assertSee('href="tel:0261915515"', false)
            ->assertSee(route('urgences.index'), false);
    }

    public function test_the_checkbox_has_a_label_a_description_and_is_not_checked_by_default(): void
    {
        $html = $this->actingAs($this->citoyen)->get(route('contact.create'))->getContent();

        $this->assertMatchesRegularExpression('/<label[^>]*for="urgence_medicale"/', $html);
        $this->assertMatchesRegularExpression('/<input id="urgence_medicale" name="urgence_medicale" type="checkbox" value="1"[^>]*aria-describedby="urgence_aide"/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="urgence_medicale"[^>]*checked/', $html);
        $this->assertStringContainsString('<legend', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_a_ticked_box_creates_the_request_with_the_medical_emergency_priority(): void
    {
        $reponse = $this->actingAs($this->citoyen)->post(route('contact.store'), $this->donnees(['urgence_medicale' => '1']));

        $demande = Demande::firstOrFail();
        $this->assertSame(Priorite::UrgenceMedicale, $demande->fresh()->priorite);
        $reponse->assertRedirect(route('contact.confirmation', $demande));
    }

    public function test_the_confirmation_repeats_the_instruction_with_the_numbers_only_for_an_emergency(): void
    {
        $this->actingAs($this->citoyen)->post(route('contact.store'), $this->donnees(['urgence_medicale' => '1']));
        $urgente = Demande::firstOrFail();

        $this->actingAs($this->citoyen)->get(route('contact.confirmation', $urgente))
            ->assertSee('Urgence médicale signalée')
            ->assertSee('Cette plateforme n\'est pas un service d\'urgence en temps réel.')
            ->assertSee('02 61 91 55 15')->assertSee($urgente->reference);

        $this->actingAs($this->citoyen)->post(route('contact.store'), $this->donnees(['objet' => 'Question ordinaire']));
        $normale = Demande::where('objet', 'Question ordinaire')->firstOrFail();
        $this->actingAs($this->citoyen)->get(route('contact.confirmation', $normale))
            ->assertDontSee('Urgence médicale signalée')->assertDontSee('service d\'urgence en temps réel');
    }

    public function test_without_the_box_or_with_zero_the_priority_is_normal(): void
    {
        $this->actingAs($this->citoyen)->post(route('contact.store'), $this->donnees(['objet' => 'Sans case']));
        $this->actingAs($this->citoyen)->post(route('contact.store'), $this->donnees(['objet' => 'Case décochée', 'urgence_medicale' => '0']));

        $this->assertSame([Priorite::Normale, Priorite::Normale], Demande::orderBy('id')->get()->map(fn ($d) => $d->fresh()->priorite)->all());
    }

    public function test_the_citizen_cannot_set_any_other_priority_and_a_bad_value_is_refused(): void
    {
        $this->actingAs($this->citoyen)->post(route('contact.store'), $this->donnees(['priorite' => 'prioritaire']));
        $this->assertSame(Priorite::Normale, Demande::firstOrFail()->fresh()->priorite);

        $this->actingAs($this->citoyen)->post(route('contact.store'), $this->donnees(['urgence_medicale' => 'peut-être']))->assertSessionHasErrors('urgence_medicale');
        $this->assertSame(1, Demande::count());
    }

    public function test_there_is_no_classification_by_keywords(): void
    {
        $this->actingAs($this->citoyen)->post(route('contact.store'), $this->donnees([
            'objet' => 'URGENCE MÉDICALE', 'message' => 'Urgence médicale, ambulance, SAMU, douleur thoracique, au secours.',
        ]));

        $this->assertSame(Priorite::Normale, Demande::firstOrFail()->fresh()->priorite);
    }

    public function test_the_form_keeps_the_protection_and_the_emergency_box_works_with_it_on(): void
    {
        config(['securite.protection_formulaires' => true]);

        $page = $this->actingAs($this->citoyen)->get(route('contact.create'))->getContent();
        $this->assertStringContainsString('name="_jeton_formulaire"', $page);
        $this->assertStringContainsString('name="note_interne_zq"', $page);
        preg_match('/name="_jeton_formulaire" value="([^"]+)"/', $page, $m);

        $this->travel(3)->seconds();
        $this->actingAs($this->citoyen)->post(route('contact.store'), $this->donnees(['urgence_medicale' => '1', '_jeton_formulaire' => $m[1], 'note_interne_zq' => '']))
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(Priorite::UrgenceMedicale, Demande::firstOrFail()->fresh()->priorite);
        $this->travelBack();
    }

    public function test_agent_and_admin_cannot_use_the_contact_form(): void
    {
        $this->actingAs(User::factory()->agent()->create())->post(route('contact.store'), $this->donnees(['urgence_medicale' => '1']))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->post(route('contact.store'), $this->donnees(['urgence_medicale' => '1']))->assertForbidden();
        $this->assertSame(0, Demande::count());
    }

    public function test_the_emergency_numbers_are_those_of_the_urgences_page(): void
    {
        Service::factory()->create(['nom' => 'Service sans téléphone', 'actif' => true, 'urgence' => true, 'telephone' => null]);
        Service::factory()->urgence()->create(['nom' => 'Service désactivé', 'actif' => false, 'telephone' => '02 61 91 00 00']);

        $this->actingAs($this->citoyen)->get(route('contact.create'))
            ->assertSee('02 61 91 55 15')->assertDontSee('02 61 91 00 00');
    }

    public function test_the_fixed_real_emergency_sentence_is_on_the_form_the_confirmation_and_the_urgences_page(): void
    {
        $phrase = 'En cas d\'urgence réelle, appelez le 15 (SAMU) ou le 112.';

        $this->actingAs($this->citoyen)->get(route('contact.create'))->assertSee($phrase);

        $this->actingAs($this->citoyen)->post(route('contact.store'), $this->donnees(['urgence_medicale' => '1']));
        $this->actingAs($this->citoyen)->get(route('contact.confirmation', Demande::firstOrFail()))->assertSee($phrase);

        // Page publique : pour un invité, et même sans aucun service renseigné.
        $this->get(route('urgences.index'))->assertSee($phrase);
        Service::query()->delete();
        $this->get(route('urgences.index'))->assertOk()->assertSee($phrase)->assertSee('Aucun service d\'urgence ou de santé');
    }

    public function test_the_demo_services_keep_fictional_numbers_next_to_the_real_emergency_numbers(): void
    {
        $this->seed(\Database\Seeders\UrgenceSeeder::class);
        $this->seed(\Database\Seeders\NumerosFictifsSeeder::class);

        $html = $this->get(route('urgences.index'))->getContent();

        $this->assertStringContainsString('02 61 91 55 01', $html);
        $this->assertStringContainsString('02 61 91 55 15', $html);
        $this->assertStringContainsString('appelez le 15 (SAMU) ou le 112', $html);
        $this->assertStringNotContainsString('0262 55 01 15', $html);
    }}
