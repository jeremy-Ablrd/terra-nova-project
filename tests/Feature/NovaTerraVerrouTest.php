<?php

namespace Tests\Feature;

use App\Models\ApiRequest;
use App\Models\User;
use App\Services\NovaTerraApi;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;
use RuntimeException;
use Tests\TestCase;

class NovaTerraVerrouTest extends TestCase
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

    private function respondWith(int $count, array $overrides = []): void
    {
        $this->apiStatus = 200;
        $this->apiBody = [
            'session' => ['current_wave' => 1],
            'requests' => array_map(fn ($n) => array_merge([
                'id' => $n,
                'request_code' => sprintf('REQ-%04d', $n),
                'message_public' => "Message $n",
                'xp_total' => 5,
            ], $n === 1 ? $overrides : []), range(1, $count)),
        ];
    }

    private function takeLock()
    {
        $lock = Cache::lock(NovaTerraApi::LOCK_KEY, 120);
        $this->assertTrue($lock->get(), 'Le verrou aurait dû être libre au départ.');

        return $lock;
    }

    private function assertLockIsFree(): void
    {
        $lock = Cache::lock(NovaTerraApi::LOCK_KEY, 120);
        $this->assertTrue($lock->get(), 'Le verrou est resté pris.');
        $lock->release();
    }

    public function test_command_does_nothing_when_the_lock_is_taken(): void
    {
        $this->respondWith(10);
        $lock = $this->takeLock();

        $this->artisan('novaterra:sync')
            ->expectsOutput("Une synchronisation est déjà en cours : rien n'a été lancé.")
            ->assertExitCode(0);

        Http::assertNothingSent();
        $this->assertSame(0, ApiRequest::count());
        $this->assertNull(Cache::get(NovaTerraApi::CACHE_LAST_SYNC));
        $this->assertNull(Cache::get(NovaTerraApi::CACHE_LAST_ERROR));

        $lock->release();
    }

    public function test_admin_post_does_nothing_when_the_lock_is_taken_and_shows_a_status_message(): void
    {
        $this->respondWith(10);
        $lock = $this->takeLock();

        $this->actingAs(User::factory()->admin()->create())->followingRedirects()->post('/admin/synchronisation')
            ->assertOk()
            ->assertSee('Synchronisation déjà en cours')
            ->assertSee('Une synchronisation est déjà en cours. Réessayez dans quelques instants.')
            ->assertSee('role="status"', false)
            ->assertDontSee('Échec de la synchronisation');

        Http::assertNothingSent();
        $this->assertSame(0, ApiRequest::count());
        $this->assertNull(Cache::get(NovaTerraApi::CACHE_LAST_SYNC));

        $lock->release();
    }

    public function test_lock_is_released_after_a_success(): void
    {
        $this->respondWith(3);

        $this->artisan('novaterra:sync')->expectsOutput('3 demandes reçues, 3 nouvelles.')->assertExitCode(0);

        $this->assertLockIsFree();
    }

    public function test_lock_is_released_after_an_api_error_and_after_an_unexpected_exception(): void
    {
        $this->apiStatus = 403;
        $this->artisan('novaterra:sync')->assertExitCode(1);
        $this->assertLockIsFree();

        Http::fake(fn () => throw new RuntimeException('boom'));
        $this->artisan('novaterra:sync')->assertExitCode(1);
        $this->assertLockIsFree();
    }

    public function test_a_failure_in_the_middle_of_a_batch_writes_nothing(): void
    {
        $this->respondWith(1);
        $this->artisan('novaterra:sync')->assertExitCode(0);
        $this->assertSame(5, ApiRequest::where('request_code', 'REQ-0001')->value('xp_total'));

        // 201 demandes = 2 lots de 200 ; REQ-0001 est modifiée dans le 1er lot, le 2e lot échoue.
        $this->respondWith(201, ['xp_total' => 999]);
        $inserts = 0;
        DB::listen(function (QueryExecuted $query) use (&$inserts) {
            if (str_contains($query->sql, 'insert into "api_requests"') && ++$inserts === 2) {
                throw new RuntimeException('échec simulé au 2e lot');
            }
        });

        $this->artisan('novaterra:sync')->assertExitCode(1);

        $this->assertSame(2, $inserts, 'Le 2e lot devait bien être tenté.');
        $this->assertSame(1, ApiRequest::count());
        $this->assertSame(5, ApiRequest::where('request_code', 'REQ-0001')->value('xp_total'));
        $this->assertLockIsFree();
    }

    public function test_command_stores_last_error_for_a_non_api_exception(): void
    {
        Http::fake(fn () => throw new RuntimeException('boom'));

        $this->artisan('novaterra:sync')->assertExitCode(1);

        $this->assertSame(NovaTerraApi::unexpectedErrorMessage(), Cache::get(NovaTerraApi::CACHE_LAST_ERROR));
    }

    public function test_command_still_stores_the_api_error_message(): void
    {
        $this->apiStatus = 403;

        $this->artisan('novaterra:sync')->assertExitCode(1);

        $this->assertStringContainsString('403', Cache::get(NovaTerraApi::CACHE_LAST_ERROR));
    }

    public function test_with_lock_can_wrap_other_steps_such_as_a_future_import(): void
    {
        $api = app(NovaTerraApi::class);

        // Verrou libre : le travail s'exécute, le verrou est libéré ensuite.
        $this->assertSame(['busy' => false, 'received' => 7, 'new' => 3], $api->withLock(fn () => ['received' => 7, 'new' => 3]));
        $this->assertLockIsFree();

        // Verrou pris : le travail n'est pas exécuté.
        $lock = $this->takeLock();
        $ran = false;
        $result = $api->withLock(function () use (&$ran) {
            $ran = true;

            return ['received' => 1, 'new' => 1];
        });
        $this->assertTrue($result['busy']);
        $this->assertFalse($ran);
        $lock->release();

        // Exception dans le travail : mémorisée, relancée, verrou libéré.
        try {
            $api->withLock(fn () => throw new RuntimeException('import cassé'));
            $this->fail('L\'exception aurait dû être relancée.');
        } catch (RuntimeException $e) {
            $this->assertSame('import cassé', $e->getMessage());
        }
        $this->assertSame(NovaTerraApi::unexpectedErrorMessage(), Cache::get(NovaTerraApi::CACHE_LAST_ERROR));
        $this->assertLockIsFree();
    }

    public function test_scheduled_task_overlap_protection_expires_after_five_minutes(): void
    {
        $event = collect(Schedule::events())->first(fn ($e) => str_contains($e->command, 'novaterra:sync'));

        $this->assertNotNull($event);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame(5, $event->expiresAt);
    }
}
