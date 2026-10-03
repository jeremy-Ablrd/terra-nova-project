<?php

namespace Tests\Feature;

use App\Models\ApiRequest;
use App\Models\User;
use App\Services\NovaTerraApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SynchronisationAdminTest extends TestCase
{
    use RefreshDatabase;

    private array $apiBody = [];

    private int $apiStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.webcup.key' => 'cle-de-test']);
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response($this->apiBody, $this->apiStatus));
    }

    private function respondWith(int $count): void
    {
        $this->apiStatus = 200;
        $this->apiBody = [
            'api_version' => '1.0',
            'session' => ['current_wave' => 3, 'minutes_until_next_wave' => 42, 'visible_requests_count' => $count],
            'requests' => array_map(fn ($n) => [
                'id' => $n,
                'request_code' => sprintf('REQ-%03d', $n),
                'message_public' => "Message $n",
                'xp_total' => 100,
            ], range(1, $count)),
        ];
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/synchronisation')->assertRedirect('/login');
        $this->post('/admin/synchronisation')->assertRedirect('/login');

        Http::assertNothingSent();
    }

    public function test_citizen_and_agent_get_403_on_get_and_post_without_any_api_call(): void
    {
        foreach ([User::factory()->create(), User::factory()->agent()->create()] as $user) {
            $this->actingAs($user)->get('/admin/synchronisation')->assertForbidden();
            $this->actingAs($user)->post('/admin/synchronisation')->assertForbidden();
        }

        Http::assertNothingSent();
        $this->assertSame(0, ApiRequest::count());
    }

    public function test_admin_sees_the_page_with_empty_state(): void
    {
        $this->actingAs($this->admin())->get('/admin/synchronisation')
            ->assertOk()
            ->assertSee('Synchronisation des demandes')
            ->assertSee('Jamais')
            ->assertSee('Aucune')
            ->assertSee('Aucune information de session')
            ->assertSee('Actualiser maintenant');
    }

    public function test_admin_post_with_valid_response_stores_rows_and_shows_result_in_page(): void
    {
        $this->respondWith(10);

        $this->actingAs($this->admin())->post('/admin/synchronisation')
            ->assertRedirect(route('admin.synchronisation.index'));

        $this->assertSame(10, ApiRequest::count());
        $this->assertNotNull(Cache::get(NovaTerraApi::CACHE_LAST_SYNC));
        $this->assertNull(Cache::get(NovaTerraApi::CACHE_LAST_ERROR));

        // Résultat immédiat (après la redirection), puis état persistant au rechargement.
        $this->actingAs($this->admin())->followingRedirects()->post('/admin/synchronisation')
            ->assertSee('Synchronisation réussie')
            ->assertSee('10 demandes reçues, 0 nouvelles.');

        $this->actingAs($this->admin())->get('/admin/synchronisation')
            ->assertDontSee('Jamais')
            ->assertSee('Vague actuelle')
            ->assertSee('42 min')
            ->assertDontSee('Message 1'); // pas de liste du contenu des demandes
    }

    public function test_replayed_post_creates_no_duplicates_and_keeps_first_seen_at(): void
    {
        $this->respondWith(10);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/synchronisation');
        $before = ApiRequest::orderBy('id')->pluck('first_seen_at', 'request_code')->map->toDateTimeString();

        $this->travelTo(now()->addMinutes(5));
        $this->actingAs($admin)->post('/admin/synchronisation');

        $this->assertSame(10, ApiRequest::count());
        $this->assertEquals($before, ApiRequest::orderBy('id')->pluck('first_seen_at', 'request_code')->map->toDateTimeString());
    }

    public function test_post_with_403_keeps_rows_stores_error_and_shows_it_without_a_500(): void
    {
        $this->respondWith(10);
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/synchronisation');

        $this->apiStatus = 403;
        $this->apiBody = ['message' => 'forbidden'];

        $this->actingAs($admin)->post('/admin/synchronisation')
            ->assertRedirect(route('admin.synchronisation.index'));

        $this->assertSame(10, ApiRequest::count());
        $this->assertStringContainsString('403', Cache::get(NovaTerraApi::CACHE_LAST_ERROR));

        $this->actingAs($admin)->get('/admin/synchronisation')
            ->assertOk()
            ->assertSee('403');
    }

    public function test_failure_message_is_shown_in_the_page_right_after_the_post(): void
    {
        $this->apiStatus = 403;

        $this->actingAs($this->admin())->followingRedirects()->post('/admin/synchronisation')
            ->assertOk()
            ->assertSee('Échec de la synchronisation')
            ->assertSee('accès refusé (403)')
            ->assertSee('Les demandes déjà enregistrées sont conservées.');
    }

    public function test_unexpected_exception_does_not_produce_a_500(): void
    {
        Http::fake(fn () => throw new \RuntimeException('boom'));

        $this->actingAs($this->admin())->followingRedirects()->post('/admin/synchronisation')
            ->assertOk()
            ->assertSee('Échec de la synchronisation')
            ->assertSee('Erreur inattendue');
    }

    public function test_seventh_post_within_a_minute_is_throttled(): void
    {
        $this->respondWith(2);
        $admin = $this->admin();

        for ($i = 1; $i <= 6; $i++) {
            $this->actingAs($admin)->post('/admin/synchronisation')->assertRedirect();
        }

        $this->actingAs($admin)->post('/admin/synchronisation')->assertStatus(429);
    }

    public function test_admin_navigation_links_to_the_page_and_others_do_not_see_it(): void
    {
        $this->actingAs($this->admin())->get('/admin')
            ->assertSee(route('admin.synchronisation.index'));

        $this->actingAs(User::factory()->agent()->create())->get('/agent')
            ->assertDontSee(route('admin.synchronisation.index'));
        $this->actingAs(User::factory()->create())->get('/espace')
            ->assertDontSee(route('admin.synchronisation.index'));
    }
}
