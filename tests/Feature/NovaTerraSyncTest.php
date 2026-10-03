<?php

namespace Tests\Feature;

use App\Models\ApiRequest;
use App\Services\NovaTerraApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class NovaTerraSyncTest extends TestCase
{
    use RefreshDatabase;

    /** Réponse et statut renvoyés par le faux serveur : modifiables entre deux synchros. */
    private array $apiBody = [];

    private int $apiStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.webcup.key' => 'cle-de-test']);
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response($this->apiBody, $this->apiStatus));
    }

    private function fakeRequest(int $n, array $overrides = []): array
    {
        return array_merge([
            'id' => $n,
            'request_code' => sprintf('REQ-%03d', $n),
            'requester_name' => "Habitant $n",
            'requester_type' => 'citoyen',
            'message_public' => "Message $n",
            'difficulty_level' => 2,
            'difficulty' => 'moyen',
            'xp_base' => 100,
            'xp_time_bonus' => 20,
            'xp_total' => 120,
            'xp_available' => 120,
            'group_name' => 'Groupe A',
            'sort_order' => $n,
            'visible_since_wave' => 0,
            'arrival_type' => 'delay',
            'arrival_time' => '02:00:00',
        ], $overrides);
    }

    private function respondWith(int $count, array $session = ['name' => 'Session test']): void
    {
        $this->apiStatus = 200;
        $this->apiBody = [
            'api_version' => '1.0',
            'session' => $session,
            'requests' => array_map(fn ($n) => $this->fakeRequest($n), range(1, $count)),
        ];
    }

    public function test_ten_requests_create_ten_rows_with_first_seen_at(): void
    {
        $this->respondWith(10);

        $this->artisan('novaterra:sync')
            ->expectsOutput('10 demandes reçues, 10 nouvelles.')
            ->assertExitCode(0);

        $this->assertSame(10, ApiRequest::count());
        $this->assertSame(0, ApiRequest::whereNull('first_seen_at')->count());

        $row = ApiRequest::where('request_code', 'REQ-001')->first();
        $this->assertSame('02:00:00', $row->arrival_time);
        $this->assertSame(1, $row->sort_order);
        $this->assertSame(0, $row->visible_since_wave);
        $this->assertSame('Message 1', $row->payload['message_public']);

        Http::assertSent(fn (Request $r) => $r->hasHeader('X-Webcup-Api-Key', 'cle-de-test'));
        $this->assertSame(['name' => 'Session test'], Cache::get(NovaTerraApi::CACHE_SESSION));
        $this->assertNotNull(Cache::get(NovaTerraApi::CACHE_LAST_SYNC));
        $this->assertNull(Cache::get(NovaTerraApi::CACHE_LAST_ERROR));
    }

    public function test_replaying_the_same_response_keeps_rows_and_first_seen_at(): void
    {
        $this->respondWith(10);
        $this->artisan('novaterra:sync')->assertExitCode(0);
        $before = ApiRequest::orderBy('id')->pluck('first_seen_at', 'request_code')->map->toDateTimeString();

        $this->travelTo(now()->addMinutes(5));
        $this->apiBody['requests'][0]['xp_total'] = 999;

        $this->artisan('novaterra:sync')
            ->expectsOutput('10 demandes reçues, 0 nouvelles.')
            ->assertExitCode(0);

        $this->assertSame(10, ApiRequest::count());
        $after = ApiRequest::orderBy('id')->pluck('first_seen_at', 'request_code')->map->toDateTimeString();
        $this->assertEquals($before, $after);
        $this->assertSame(999, ApiRequest::where('request_code', 'REQ-001')->value('xp_total'));
    }

    public function test_a_new_request_gets_a_more_recent_first_seen_at(): void
    {
        $this->respondWith(10);
        $this->artisan('novaterra:sync')->assertExitCode(0);

        $this->travelTo(now()->addMinutes(5));
        $this->respondWith(11);

        $this->artisan('novaterra:sync')
            ->expectsOutput('11 demandes reçues, 1 nouvelles.')
            ->assertExitCode(0);

        $this->assertSame(11, ApiRequest::count());
        $new = ApiRequest::where('request_code', 'REQ-011')->first();
        $old = ApiRequest::where('request_code', 'REQ-001')->first();
        $this->assertTrue($new->first_seen_at->greaterThan($old->first_seen_at));
    }

    public function test_403_keeps_data_stores_error_and_fails_the_command(): void
    {
        $this->respondWith(10);
        $this->artisan('novaterra:sync')->assertExitCode(0);

        $this->apiStatus = 403;
        $this->apiBody = ['message' => 'forbidden'];

        $this->artisan('novaterra:sync')->assertExitCode(1);

        $this->assertSame(10, ApiRequest::count());
        $this->assertStringContainsString('403', Cache::get(NovaTerraApi::CACHE_LAST_ERROR));
        $this->assertStringContainsString('clé', Cache::get(NovaTerraApi::CACHE_LAST_ERROR));
        $this->assertSame(['name' => 'Session test'], Cache::get(NovaTerraApi::CACHE_SESSION));
        $this->assertNotNull(Cache::get(NovaTerraApi::CACHE_LAST_SYNC));
    }

    public function test_success_clears_the_previous_error(): void
    {
        $this->apiStatus = 500;
        $this->artisan('novaterra:sync')->assertExitCode(1);
        $this->assertNotNull(Cache::get(NovaTerraApi::CACHE_LAST_ERROR));

        $this->respondWith(2);
        $this->artisan('novaterra:sync')->assertExitCode(0);
        $this->assertNull(Cache::get(NovaTerraApi::CACHE_LAST_ERROR));
    }

    public function test_missing_or_null_fields_do_not_break_the_upsert(): void
    {
        $this->apiStatus = 200;
        $this->apiBody = [
            'requests' => [
                ['request_code' => 'REQ-MIN'],
                $this->fakeRequest(2, ['group_name' => null, 'sort_order' => null, 'visible_since_wave' => null, 'arrival_time' => null, 'difficulty_level' => null]),
                ['message_public' => 'sans code, ignorée'],
            ],
        ];

        $this->artisan('novaterra:sync')
            ->expectsOutput('2 demandes reçues, 2 nouvelles.')
            ->assertExitCode(0);

        $minimal = ApiRequest::where('request_code', 'REQ-MIN')->first();
        $this->assertNotNull($minimal->first_seen_at);
        $this->assertNull($minimal->group_name);
        $this->assertSame(0, $minimal->xp_total);
        $this->assertNull($minimal->sort_order);
        $this->assertNull($minimal->visible_since_wave);
        $this->assertNull(ApiRequest::where('request_code', 'REQ-002')->value('visible_since_wave'));
        $this->assertNull(ApiRequest::where('request_code', 'REQ-002')->value('arrival_time'));
        $this->assertSame(2, ApiRequest::count());
    }

    public function test_real_api_shape_is_stored_and_empty_arrival_time_becomes_null(): void
    {
        $this->apiStatus = 200;
        $this->apiBody = [
            'api_version' => '1.0',
            'session' => ['status' => 'active', 'is_running' => true, 'elapsed_minutes' => 61, 'visible_requests_count' => 1],
            'requests' => [[
                'id' => 1,
                'request_code' => 'D01',
                'requester_name' => 'Haut Conseil de la Ville',
                'requester_type' => 'Institution',
                'message_public' => 'La plateforme va accueillir les habitants de Nova Terra.',
                'difficulty' => 'Facile',
                'xp_base' => 250,
                'xp_time_bonus' => 0,
                'xp_total' => 250,
                'is_ai_related' => 0,
                'arrival_type' => 'debut',
                'wave_number' => null,
                'arrival_time' => '',
                'group_name' => 'Socle',
                'sort_order' => 1,
                'is_initial' => true,
                'is_ai_request' => false,
                'difficulty_level' => 1,
                'xp_available' => 250,
                'visible_since_wave' => 0,
            ]],
        ];

        $this->artisan('novaterra:sync')->assertExitCode(0);

        $row = ApiRequest::where('request_code', 'D01')->first();
        $this->assertSame(1, $row->api_id);
        $this->assertSame('Institution', $row->requester_type);
        $this->assertSame('Facile', $row->difficulty);
        $this->assertSame(1, $row->difficulty_level);
        $this->assertSame(250, $row->xp_total);
        $this->assertSame('Socle', $row->group_name);
        $this->assertSame('debut', $row->arrival_type);
        $this->assertNull($row->arrival_time);
        $this->assertSame(1, $row->sort_order);
        $this->assertSame(0, $row->visible_since_wave);
        $this->assertArrayHasKey('is_ai_related', $row->payload);
        $this->assertArrayHasKey('is_ai_request', $row->payload);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('api_requests', 'is_ai_related'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('api_requests', 'wave_number'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('api_requests', 'is_initial'));
        $this->assertSame(61, Cache::get(NovaTerraApi::CACHE_SESSION)['elapsed_minutes']);
    }

    public function test_empty_key_fails_without_any_http_call(): void
    {
        config(['services.webcup.key' => '']);

        $this->artisan('novaterra:sync')->assertExitCode(1);

        Http::assertNothingSent();
        $this->assertStringContainsString('WEBCUP_API_KEY', Cache::get(NovaTerraApi::CACHE_LAST_ERROR));
    }

    public function test_schedule_runs_every_thirty_seconds_only_with_a_key(): void
    {
        $event = collect(Schedule::events())->first(fn ($e) => str_contains($e->command, 'novaterra:sync'));

        $this->assertNotNull($event);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame(30, $event->repeatSeconds);

        config(['services.webcup.key' => '']);
        $this->assertFalse($event->filtersPass($this->app));

        config(['services.webcup.key' => 'cle-de-test']);
        $this->assertTrue($event->filtersPass($this->app));
    }
}
