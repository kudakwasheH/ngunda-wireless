<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\InternetPackage;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\PackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class Phase4Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, PackageSeeder::class]);
        RateLimiter::clear('x');
    }

    private function makeUser(string $roleSlug, array $attrs = []): User
    {
        $u = new User(['name' => ucfirst($roleSlug), 'email' => $roleSlug.'@test.local', 'password' => 'Password123'] + $attrs);
        $u->role_id = Role::where('slug', $roleSlug)->value('id');
        $u->is_active = $attrs['is_active'] ?? true;
        $u->save();

        return $u;
    }

    public function test_schema_contains_all_required_tables(): void
    {
        foreach (['users', 'roles', 'permissions', 'customers', 'internet_packages', 'access_tokens', 'token_redemptions',
            'network_devices', 'device_sessions', 'blocked_devices', 'bandwidth_limits', 'usage_records', 'payments',
            'sales', 'sales_items', 'network_interfaces', 'access_points', 'network_events', 'system_settings', 'audit_logs'] as $table) {
            $this->assertTrue(\Schema::hasTable($table), "missing table $table");
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/packages')->assertRedirect('/login');
    }

    public function test_login_success_and_audit(): void
    {
        $this->makeUser('super_admin');
        $this->post('/login', ['email' => 'super_admin@test.local', 'password' => 'Password123'])->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login']);
    }

    public function test_login_fails_with_wrong_password_and_is_audited(): void
    {
        $this->makeUser('super_admin');
        $this->from('/login')->post('/login', ['email' => 'super_admin@test.local', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_failed']);
    }

    public function test_login_is_rate_limited_after_five_failures(): void
    {
        $this->makeUser('super_admin');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'super_admin@test.local', 'password' => 'bad']);
        }
        // Even the correct password is refused while locked out.
        $this->post('/login', ['email' => 'super_admin@test.local', 'password' => 'Password123'])
            ->assertSessionHasErrors(['email' => 'Too many login attempts. Try again in 60 seconds.']);
        $this->assertGuest();
    }

    public function test_disabled_user_and_customer_role_cannot_log_in(): void
    {
        $this->makeUser('sales_officer', ['is_active' => false]);
        $this->post('/login', ['email' => 'sales_officer@test.local', 'password' => 'Password123']);
        $this->assertGuest();

        $this->makeUser('customer');
        $this->post('/login', ['email' => 'customer@test.local', 'password' => 'Password123']);
        $this->assertGuest();
    }

    public function test_roles_enforce_permissions(): void
    {
        $sales = $this->makeUser('sales_officer');
        $net = $this->makeUser('network_admin');
        $super = $this->makeUser('super_admin');

        $this->actingAs($sales)->get('/dashboard')->assertOk();
        $this->actingAs($sales)->get('/packages')->assertOk();
        $this->actingAs($sales)->get('/packages/create')->assertForbidden();
        $this->actingAs($sales)->get('/users')->assertForbidden();
        $this->actingAs($sales)->get('/settings')->assertForbidden();
        $this->actingAs($sales)->get('/audit')->assertForbidden();

        $this->actingAs($net)->get('/settings')->assertOk();
        $this->actingAs($net)->get('/users')->assertForbidden();
        $this->actingAs($net)->get('/packages/create')->assertForbidden();

        foreach (['/dashboard', '/packages', '/packages/create', '/users', '/users/create', '/settings', '/audit'] as $url) {
            $this->actingAs($super)->get($url)->assertOk();
        }
    }

    public function test_dashboard_shows_no_fabricated_network_data(): void
    {
        $this->actingAs($this->makeUser('super_admin'))->get('/dashboard')
            ->assertOk()
            ->assertSee('has not reported yet')
            ->assertSee('Tokens generated');
    }

    public function test_default_packages_are_seeded_with_bandwidth(): void
    {
        $this->assertSame(6, InternetPackage::count());
        $hourly = InternetPackage::where('slug', 'hourly')->first();
        $this->assertSame(60, $hourly->duration_minutes);
        $this->assertSame(500, (int) $hourly->data_limit_mb);
        $this->assertNotNull($hourly->bandwidthLimit);
    }

    public function test_super_admin_can_create_and_update_package_with_audit(): void
    {
        $super = $this->makeUser('super_admin');
        $this->actingAs($super)->post('/packages', [
            'name' => 'Night Owl', 'duration_minutes' => 480, 'data_limit_mb' => 2048, 'max_devices' => 2,
            'price' => '1.50', 'cost_price' => '0.40', 'currency' => 'usd', 'download_kbps' => 3000, 'upload_kbps' => 1500, 'is_active' => 1,
        ])->assertRedirect('/packages');

        $pkg = InternetPackage::where('name', 'Night Owl')->firstOrFail();
        $this->assertSame('night-owl', $pkg->slug);
        $this->assertSame('USD', $pkg->currency);
        $this->assertSame(3000, $pkg->bandwidthLimit->download_kbps);

        $this->actingAs($super)->put("/packages/{$pkg->id}", [
            'name' => 'Night Owl', 'duration_minutes' => 600, 'max_devices' => 2, 'price' => '2.00',
            'currency' => 'USD', 'is_active' => 1,
        ])->assertRedirect('/packages');
        $this->assertSame(600, $pkg->fresh()->duration_minutes);
        $this->assertNull($pkg->fresh()->data_limit_mb); // blank = unlimited
        $this->assertDatabaseHas('audit_logs', ['action' => 'package.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'package.updated']);
    }

    public function test_package_validation_rejects_bad_input(): void
    {
        $this->actingAs($this->makeUser('super_admin'))
            ->post('/packages', ['name' => '', 'duration_minutes' => 0, 'price' => -1, 'currency' => 'X1', 'max_devices' => 0])
            ->assertSessionHasErrors(['name', 'duration_minutes', 'price', 'currency', 'max_devices']);
    }

    public function test_sales_officer_cannot_create_package(): void
    {
        $this->actingAs($this->makeUser('sales_officer'))
            ->post('/packages', ['name' => 'X', 'duration_minutes' => 60, 'max_devices' => 1, 'price' => 1, 'currency' => 'USD'])
            ->assertForbidden();
        $this->assertDatabaseMissing('internet_packages', ['name' => 'X']);
    }

    public function test_user_management_rules(): void
    {
        $super = $this->makeUser('super_admin');
        $sales = Role::where('slug', 'sales_officer')->value('id');
        $customerRole = Role::where('slug', 'customer')->value('id');

        // weak password rejected
        $this->actingAs($super)->post('/users', ['name' => 'N', 'email' => 'n@t.l', 'role_id' => $sales,
            'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        // customer (non-staff) role cannot be assigned to a staff login
        $this->actingAs($super)->post('/users', ['name' => 'N', 'email' => 'n@t.l', 'role_id' => $customerRole,
            'password' => 'GoodPassw0rdX', 'password_confirmation' => 'GoodPassw0rdX'])->assertSessionHasErrors('role_id');
        // valid
        $this->actingAs($super)->post('/users', ['name' => 'N', 'email' => 'n@t.l', 'role_id' => $sales,
            'password' => 'GoodPassw0rdX', 'password_confirmation' => 'GoodPassw0rdX'])->assertRedirect('/users');
        $created = User::where('email', 'n@t.l')->first();
        $this->assertNotSame('GoodPassw0rdX', $created->password); // hashed
        $this->assertTrue($created->is_active);

        // cannot demote / disable the only super admin, nor self-deactivate
        $this->actingAs($super)->put("/users/{$super->id}", ['name' => 'S', 'email' => $super->email, 'role_id' => $sales, 'is_active' => 1])
            ->assertSessionHasErrors('role_id');
        $this->actingAs($super)->put("/users/{$super->id}", ['name' => 'S', 'email' => $super->email,
            'role_id' => $super->role_id, 'is_active' => 0])->assertSessionHasErrors();
        $this->assertTrue($super->fresh()->is_active);
    }

    public function test_user_audit_does_not_store_password(): void
    {
        $super = $this->makeUser('super_admin');
        $this->actingAs($super)->post('/users', ['name' => 'N', 'email' => 'n@t.l',
            'role_id' => Role::where('slug', 'sales_officer')->value('id'),
            'password' => 'GoodPassw0rdX', 'password_confirmation' => 'GoodPassw0rdX']);
        $this->assertStringNotContainsString('GoodPassw0rdX', json_encode(AuditLog::all()->toArray()));
    }

    public function test_settings_validation_and_secret_encryption(): void
    {
        $net = $this->makeUser('network_admin');
        $base = [
            'business_name' => 'Ngunda Wireless', 'currency' => 'USD', 'portal_primary_color' => '#112233',
            'token_length' => 10, 'token_max_failed_attempts' => 5, 'session_idle_timeout_minutes' => 30,
            'wifi_ssid' => 'Ngunda Wireless', 'wifi_security' => 'wpa2', 'wifi_passphrase' => 'supersecret123',
            'wan_interface' => 'enp1s0', 'lan_interface' => 'wlan0', 'gateway_ip' => '10.10.0.1',
            'dhcp_start' => '10.10.0.50', 'dhcp_end' => '10.10.3.250', 'dns_servers' => '1.1.1.1,9.9.9.9',
        ];
        $this->actingAs($net)->put('/settings', $base)->assertSessionHasNoErrors();
        $this->assertSame('Ngunda Wireless', \App\Support\Settings::get('wifi.ssid'));
        $this->assertSame('supersecret123', \App\Support\Settings::get('wifi.passphrase'));
        $raw = \App\Models\SystemSetting::where('key', 'wifi.passphrase')->value('value');
        $this->assertStringNotContainsString('supersecret123', $raw); // stored encrypted

        // shell-injection style interface name, bad IP, inverted DHCP range, same WAN/LAN
        foreach ([
            ['wan_interface' => 'eth0;reboot'], ['gateway_ip' => '999.1.1.1'], ['dns_servers' => '1.1.1.1;ls'],
            ['dhcp_start' => '10.10.3.250', 'dhcp_end' => '10.10.0.50'], ['lan_interface' => 'enp1s0'],
        ] as $bad) {
            $this->actingAs($net)->put('/settings', array_merge($base, $bad))->assertSessionHasErrors();
        }
    }

    public function test_audit_log_is_append_only(): void
    {
        $log = AuditLog::create(['action' => 'test.action']);
        $log->action = 'tampered';
        $this->assertFalse($log->save());
        $this->assertFalse((bool) $log->delete());
        $this->assertDatabaseHas('audit_logs', ['action' => 'test.action']);
    }

    public function test_customer_cannot_use_admin_even_with_session(): void
    {
        $cust = $this->makeUser('customer');
        $this->actingAs($cust)->get('/dashboard')->assertForbidden();
    }

    public function test_csrf_middleware_is_part_of_the_web_group(): void
    {
        // The framework skips CSRF checks while running tests, so assert it is wired into the web group.
        $groups = app(\Illuminate\Contracts\Http\Kernel::class)->getMiddlewareGroups();
        $this->assertContains(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, $groups['web']);
    }
}