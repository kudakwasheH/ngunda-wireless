<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\BlockedDevice;
use App\Models\DeviceSession;
use App\Models\InternetPackage;
use App\Models\NetworkDevice;
use App\Models\NetworkEvent;
use App\Models\Role;
use App\Models\TokenRedemption;
use App\Models\User;
use App\Services\Network\GatewayException;
use App\Services\Network\NetworkGateway;
use App\Services\Tokens\RedemptionService;
use App\Services\Tokens\SessionEnforcer;
use App\Services\Tokens\TokenException;
use App\Services\Tokens\TokenService;
use Database\Seeders\PackageSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class Phase5TokenTest extends TestCase
{
    use RefreshDatabase;

    private FakeGateway $gw;
    private const MAC1 = 'aa:bb:cc:dd:ee:01';
    private const MAC2 = 'aa:bb:cc:dd:ee:02';
    private const MAC3 = 'aa:bb:cc:dd:ee:03';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, PackageSeeder::class]);
        $this->gw = new FakeGateway();
        $this->app->instance(NetworkGateway::class, $this->gw);
    }

    private function pkg(string $slug = 'hourly'): InternetPackage
    {
        return InternetPackage::where('slug', $slug)->firstOrFail();
    }

    /** @return array{0:AccessToken,1:string} */
    private function makeToken(string $slug = 'hourly', array $opts = []): array
    {
        $r = app(TokenService::class)->generate($this->pkg($slug), 1, $opts);

        return [AccessToken::where('code_hash', TokenService::hash($r['codes'][0]))->firstOrFail(), $r['codes'][0]];
    }

    private function redeem(string $code, string $mac = self::MAC1, string $ip = '10.10.0.60')
    {
        return app(RedemptionService::class)->redeem($code, $mac, $ip, 'phpunit');
    }

    private function assertRefused(string $reason, string $code, string $mac = self::MAC1): void
    {
        try {
            $this->redeem($code, $mac);
            $this->fail("Expected redemption to be refused with '$reason'");
        } catch (TokenException $e) {
            $this->assertSame($reason, $e->reason);
        }
    }

    // ------------------------------------------------------------------ generation

    public function test_codes_are_unique_secure_and_stored_hashed(): void
    {
        $r = app(TokenService::class)->generate($this->pkg(), 300);
        $this->assertCount(300, array_unique($r['codes']));
        $this->assertSame(300, AccessToken::count());
        $alphabet = config('nws.token_alphabet');
        foreach ($r['codes'] as $c) {
            $this->assertSame(10, strlen($c));
            $this->assertMatchesRegularExpression('/^['.$alphabet.']+$/', $c);
        }
        // plaintext never in the DB; hash lookup works; hint is last 4
        $dump = json_encode(\Illuminate\Support\Facades\DB::table('access_tokens')->get()->all());
        $this->assertStringNotContainsString($r['codes'][0], $dump);
        $first = AccessToken::where('code_hash', TokenService::hash($r['codes'][0]))->first();
        $this->assertNotNull($first);
        $this->assertSame(substr($r['codes'][0], -4), $first->code_hint);
        $this->assertSame(64, strlen($first->code_hash));
    }

    public function test_normalization_accepts_dashes_spaces_and_lowercase(): void
    {
        [, $code] = $this->makeToken();
        $this->assertSame(TokenService::hash($code), TokenService::hash(strtolower(TokenService::format($code))));
        $this->assertSame(TokenService::hash($code), TokenService::hash(' '.TokenService::format($code).' '));
    }

    public function test_package_terms_are_snapshotted_and_custom_overrides_work(): void
    {
        [$t] = $this->makeToken('daily');
        $this->assertSame(1440, $t->duration_minutes);
        $this->assertSame(5120 * 1048576, $t->data_limit_bytes);
        $this->pkg('daily')->update(['duration_minutes' => 5]); // later edits must not alter issued tokens
        $this->assertSame(1440, $t->fresh()->duration_minutes);

        [$c] = $this->makeToken('daily', ['duration_minutes' => 90, 'data_limit_mb' => null, 'max_devices' => 4, 'is_promotional' => true]);
        $this->assertSame(90, $c->duration_minutes);
        $this->assertNull($c->data_limit_bytes);
        $this->assertSame(4, $c->max_devices);
        $this->assertTrue($c->is_promotional);
    }

    public function test_device_restricted_token_must_be_single_and_normalises_mac(): void
    {
        [$t] = $this->makeToken('hourly', ['restricted_mac' => 'AA-BB-CC-DD-EE-01']);
        $this->assertSame(self::MAC1, $t->restricted_mac);
        $this->expectException(\InvalidArgumentException::class);
        app(TokenService::class)->generate($this->pkg(), 2, ['restricted_mac' => self::MAC1]);
    }

    // ------------------------------------------------------------------ redemption

    public function test_valid_token_activates_on_first_use_and_authorizes_with_limits(): void
    {
        [$t, $code] = $this->makeToken('hourly');
        $this->assertSame('unused', $t->status);
        $this->assertNotNull($t->code_encrypted);

        $session = $this->redeem($code);

        $t->refresh();
        $this->assertSame('active', $t->status);
        $this->assertNotNull($t->activated_at);
        $this->assertEqualsWithDelta(60 * 60, $t->activated_at->diffInSeconds($t->expires_at), 2);
        $this->assertNull($t->code_encrypted, 'recoverable copy must be purged on first use');
        $this->assertSame('active', $session->status);
        $this->assertSame(self::MAC1, $session->mac);
        $this->assertTrue($session->ends_at->equalTo($t->expires_at));

        $this->assertCount(1, $this->gw->authorized);
        $limits = $this->gw->authorized[0]['limits'];
        $this->assertSame(4000, $limits['download_kbps']);
        $this->assertSame(2000, $limits['upload_kbps']);
        $this->assertSame(500 * 1048576, $limits['quota_bytes']);
        $this->assertEqualsWithDelta(3600, $limits['session_seconds'], 3);

        $this->assertDatabaseHas('token_redemptions', ['access_token_id' => $t->id, 'mac' => self::MAC1, 'is_first_use' => true]);
        $this->assertDatabaseHas('network_devices', ['mac' => self::MAC1, 'status' => 'online']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'token.redeemed']);
    }

    public function test_invalid_token_is_rejected_and_never_authorizes(): void
    {
        $this->assertRefused('invalid', 'ZZZZZZZZZZ');
        $this->assertRefused('invalid', 'short');
        $this->assertCount(0, $this->gw->authorized);
        $this->assertSame(0, DeviceSession::count());
    }

    public function test_repeated_invalid_attempts_lock_the_device_out(): void
    {
        [, $good] = $this->makeToken();
        for ($i = 0; $i < 5; $i++) {
            $this->assertRefused('invalid', 'GUESS'.$i.'ABCDE');
        }
        $this->assertRefused('locked', $good); // even a valid code is refused while locked
        $this->assertCount(0, $this->gw->authorized);
        // another device is unaffected
        $this->assertNotNull($this->redeem($good, self::MAC2, '10.10.0.99'));
    }

    public function test_expired_token_is_rejected(): void
    {
        [$t, $code] = $this->makeToken();
        $this->redeem($code);
        $this->travel(61)->minutes();
        $this->assertRefused('expired', $code);
    }

    public function test_unused_voucher_past_redeem_by_date_is_rejected(): void
    {
        [, $code] = $this->makeToken('hourly', ['valid_until' => now()->addDay()]);
        $this->travel(2)->days();
        $this->assertRefused('expired', $code);
    }

    public function test_same_device_reconnect_reuses_redemption_and_replaces_old_session(): void
    {
        [$t, $code] = $this->makeToken();
        $s1 = $this->redeem($code);
        $s2 = $this->redeem($code, self::MAC1, '10.10.0.77');

        $this->assertSame(1, TokenRedemption::where('access_token_id', $t->id)->count());
        $this->assertSame('ended', $s1->fresh()->status);
        $this->assertSame('replaced', $s1->fresh()->end_reason);
        $this->assertSame('active', $s2->fresh()->status);
        $this->assertSame(1, DeviceSession::where('status', 'active')->count());
        // expiry is NOT extended by reconnecting
        $this->assertTrue($t->fresh()->expires_at->equalTo($s2->ends_at));
    }

    public function test_device_limit_is_enforced_and_second_device_allowed_when_package_permits(): void
    {
        [, $code] = $this->makeToken('hourly');            // max 1 device
        $this->redeem($code, self::MAC1);
        $this->assertRefused('device_limit', $code, self::MAC2);
        $this->assertSame(1, TokenRedemption::count());

        [$t2, $code2] = $this->makeToken('daily');          // max 2 devices
        $this->redeem($code2, self::MAC1, '10.10.0.60');
        $this->redeem($code2, self::MAC2, '10.10.0.61');
        $this->assertRefused('device_limit', $code2, self::MAC3);
        $this->assertSame(2, TokenRedemption::where('access_token_id', $t2->id)->count());
    }

    public function test_second_redemption_of_single_device_token_cannot_succeed_after_first(): void
    {
        // Logical model of the race: the row lock serialises these; the loser sees committed state.
        [, $code] = $this->makeToken('hourly');
        $winners = 0;
        foreach ([self::MAC1, self::MAC2, self::MAC3] as $mac) {
            try {
                $this->redeem($code, $mac);
                $winners++;
            } catch (TokenException) {
            }
        }
        $this->assertSame(1, $winners);
        $this->assertSame(1, DeviceSession::where('status', 'active')->count());
    }

    public function test_device_restricted_token_refuses_other_devices(): void
    {
        [, $code] = $this->makeToken('hourly', ['restricted_mac' => self::MAC1]);
        $this->assertRefused('wrong_device', $code, self::MAC2);
        $this->assertNotNull($this->redeem($code, self::MAC1));
    }

    public function test_blocked_device_cannot_redeem(): void
    {
        [, $code] = $this->makeToken();
        $dev = NetworkDevice::create(['mac' => self::MAC1, 'status' => 'blocked']);
        BlockedDevice::create(['network_device_id' => $dev->id, 'mac' => self::MAC1, 'blocked_at' => now(), 'active_device_id' => $dev->id]);
        $this->assertRefused('blocked', $code);
        $this->assertCount(0, $this->gw->authorized);
    }

    public function test_randomized_mac_is_flagged(): void
    {
        [, $code] = $this->makeToken();
        $this->redeem($code, '02:11:22:33:44:55');   // locally-administered bit set
        $this->assertTrue(NetworkDevice::where('mac', '02:11:22:33:44:55')->first()->mac_is_randomized);
        $this->assertFalse(NetworkDevice::isRandomizedMac('00:11:22:33:44:55'));
    }

    public function test_gateway_failure_rolls_back_activation(): void
    {
        [$t, $code] = $this->makeToken();
        $this->gw->failAuthorize = true;
        try {
            $this->redeem($code);
            $this->fail('should throw');
        } catch (GatewayException) {
        }
        $t->refresh();
        $this->assertSame('unused', $t->status, 'token must not be burned when enforcement failed');
        $this->assertNull($t->activated_at);
        $this->assertSame(0, DeviceSession::count());
        $this->assertSame(0, TokenRedemption::count());
        $this->assertDatabaseHas('network_events', ['type' => 'authorize_failed']);

        $this->gw->failAuthorize = false; // customer can retry successfully
        $this->assertNotNull($this->redeem($code));
    }

    public function test_default_gateway_fails_closed(): void
    {
        $this->app->bind(NetworkGateway::class, \App\Services\Network\UnavailableGateway::class);
        [$t, $code] = $this->makeToken();
        $this->expectException(GatewayException::class);
        try {
            app(RedemptionService::class)->redeem($code, self::MAC1, '10.10.0.60');
        } finally {
            $this->assertSame('unused', $t->fresh()->status);
            $this->assertSame(0, DeviceSession::count());
        }
    }

    // ------------------------------------------------------------------ revoke / suspend

    public function test_revoke_ends_sessions_deauthorizes_and_blocks_redemption(): void
    {
        [$t, $code] = $this->makeToken();
        $s = $this->redeem($code);
        app(SessionEnforcer::class)->revoke($t, null, 'fraud');

        $this->assertSame('revoked', $t->fresh()->status);
        $this->assertSame('ended', $s->fresh()->status);
        $this->assertSame('revoked', $s->fresh()->end_reason);
        $this->assertSame('revoked', $this->gw->deauthorized[0]['reason']);
        $this->assertRefused('revoked', $code);
    }

    public function test_revoke_still_succeeds_in_db_when_gateway_is_down_and_logs_event(): void
    {
        [$t, $code] = $this->makeToken();
        $this->redeem($code);
        $this->gw->failDeauthorize = true;
        app(SessionEnforcer::class)->revoke($t, null);
        $this->assertSame('revoked', $t->fresh()->status);
        $this->assertSame(0, DeviceSession::where('status', 'active')->count());
        $this->assertSame(1, NetworkEvent::where('type', 'deauth_failed')->count());
    }

    public function test_suspend_and_unsuspend(): void
    {
        [$t, $code] = $this->makeToken();
        $this->redeem($code);
        $enf = app(SessionEnforcer::class);
        $enf->suspend($t);
        $this->assertSame('suspended', $t->fresh()->status);
        $this->assertSame(0, DeviceSession::where('status', 'active')->count());
        $this->assertRefused('suspended', $code);
        $enf->unsuspend($t);
        $this->assertSame('active', $t->fresh()->status);
        $this->assertNotNull($this->redeem($code));
    }

    // ------------------------------------------------------------------ usage / exhaustion / expiry

    public function test_data_exhaustion_suspends_session_and_blocks_reuse(): void
    {
        [$t, $code] = $this->makeToken('hourly', ['data_limit_mb' => 10]);
        $s = $this->redeem($code);
        $enf = app(SessionEnforcer::class);

        $this->assertFalse($enf->recordUsage($s, 4 * 1048576, 1 * 1048576)); // 5 MB used
        $this->assertSame('active', $t->fresh()->status);
        $this->assertEqualsWithDelta(5 * 1048576, $t->fresh()->data_used_bytes, 1);

        $this->assertTrue($enf->recordUsage($s, 4 * 1048576, 2 * 1048576));  // 11 MB >= 10 MB
        $this->assertSame('exhausted', $t->fresh()->status);
        $this->assertSame('ended', $s->fresh()->status);
        $this->assertSame('exhausted', $s->fresh()->end_reason);
        $this->assertSame('exhausted', end($this->gw->deauthorized)['reason']);
        $this->assertSame(8 * 1048576, (int) $s->fresh()->bytes_down);
        $this->assertDatabaseCount('usage_records', 2);
        $this->assertRefused('exhausted', $code);
    }

    public function test_remaining_quota_is_passed_on_reconnect(): void
    {
        [, $code] = $this->makeToken('hourly', ['data_limit_mb' => 100]);
        $s = $this->redeem($code);
        app(SessionEnforcer::class)->recordUsage($s, 30 * 1048576, 0);
        $this->redeem($code, self::MAC1, '10.10.0.88');
        $this->assertSame(70 * 1048576, end($this->gw->authorized)['limits']['quota_bytes']);
    }

    public function test_sweep_expires_tokens_and_closes_sessions(): void
    {
        [$t, $code] = $this->makeToken('hourly');
        $s = $this->redeem($code);
        $this->assertSame(0, app(SessionEnforcer::class)->sweep());

        $this->travel(61)->minutes();
        $this->assertGreaterThanOrEqual(1, app(SessionEnforcer::class)->sweep());
        $this->assertSame('expired', $t->fresh()->status);
        $this->assertSame('ended', $s->fresh()->status);
        $this->assertSame('expired', $s->fresh()->end_reason);
        $this->assertSame('expired', end($this->gw->deauthorized)['reason']);
    }

    public function test_sweep_expires_unredeemed_vouchers_past_valid_until(): void
    {
        [$t] = $this->makeToken('hourly', ['valid_until' => now()->addHour()]);
        $this->travel(2)->hours();
        app(SessionEnforcer::class)->sweep();
        $this->assertSame('expired', $t->fresh()->status);
    }

    // ------------------------------------------------------------------ admin UI / permissions

    private function staff(string $slug): User
    {
        $u = new User(['name' => $slug, 'email' => $slug.'@t.l', 'password' => 'Password123']);
        $u->role_id = Role::where('slug', $slug)->value('id');
        $u->is_active = true;
        $u->save();

        return $u;
    }

    public function test_sales_officer_generates_and_prints_vouchers_with_qr(): void
    {
        $sales = $this->staff('sales_officer');
        $resp = $this->actingAs($sales)->post('/tokens', [
            'internet_package_id' => $this->pkg('standard')->id, 'quantity' => 5, 'name' => 'Shop batch',
        ]);
        $batch = \App\Models\TokenBatch::firstOrFail();
        $resp->assertRedirect(route('tokens.batches.show', $batch));
        $this->assertSame(5, AccessToken::where('token_batch_id', $batch->id)->count());

        $page = $this->actingAs($sales)->get(route('tokens.batches.print', $batch))->assertOk();
        $page->assertSee('<svg', false)->assertSee('Shop batch', false);
        $code = \Illuminate\Support\Facades\Crypt::decryptString(AccessToken::first()->code_encrypted);
        $page->assertSee(TokenService::format($code));
        $this->assertNotNull($batch->fresh()->printed_at);
    }

    public function test_network_admin_cannot_generate_or_print_but_can_revoke(): void
    {
        $net = $this->staff('network_admin');
        [$t] = $this->makeToken();
        $this->actingAs($net)->get('/tokens/create')->assertForbidden();
        $this->actingAs($net)->post('/tokens', ['internet_package_id' => $this->pkg()->id, 'quantity' => 1])->assertForbidden();
        $this->actingAs($net)->post("/tokens/{$t->id}/revoke", ['reason' => 'abuse'])->assertRedirect();
        $this->assertSame('revoked', $t->fresh()->status);
    }

    public function test_sales_officer_cannot_revoke(): void
    {
        [$t] = $this->makeToken();
        $this->actingAs($this->staff('sales_officer'))->post("/tokens/{$t->id}/revoke")->assertForbidden();
        $this->assertSame('unused', $t->fresh()->status);
    }

    public function test_token_list_shows_only_masked_codes_and_search_works(): void
    {
        [$t, $code] = $this->makeToken();
        $sales = $this->staff('sales_officer');
        $r = $this->actingAs($sales)->get('/tokens')->assertOk();
        $r->assertDontSee($code);
        $this->actingAs($sales)->get('/tokens?q='.$t->code_hint)->assertSee('••••••'.$t->code_hint);
        $this->actingAs($sales)->get('/tokens?q='.urlencode(TokenService::format($code)))->assertSee('••••••'.$t->code_hint);
        $this->actingAs($sales)->get('/tokens?q=ZZZZ')->assertSee('No tokens found');
    }

    public function test_generation_validation(): void
    {
        $sales = $this->staff('sales_officer');
        $this->actingAs($sales)->post('/tokens', ['internet_package_id' => 999, 'quantity' => 0])
            ->assertSessionHasErrors(['internet_package_id', 'quantity']);
        $this->actingAs($sales)->post('/tokens', ['internet_package_id' => $this->pkg()->id, 'quantity' => 2, 'restricted_mac' => self::MAC1])
            ->assertSessionHasErrors('quantity');
        $this->actingAs($sales)->post('/tokens', ['internet_package_id' => $this->pkg()->id, 'quantity' => 1, 'restricted_mac' => 'not-a-mac'])
            ->assertSessionHasErrors('restricted_mac');
    }
}
