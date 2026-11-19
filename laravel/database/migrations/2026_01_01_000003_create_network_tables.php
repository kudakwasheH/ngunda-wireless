<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_interfaces', function (Blueprint $t) {
            $t->id();
            $t->string('name', 30)->unique();                   // eth0, wlan0 ...
            $t->enum('role', ['wan', 'lan', 'wlan', 'management', 'other'])->default('other');
            $t->string('mac', 17)->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->string('state', 20)->nullable();                // up/down (reported by netd)
            $t->unsignedInteger('speed_mbps')->nullable();
            $t->unsignedBigInteger('rx_bytes')->default(0);
            $t->unsignedBigInteger('tx_bytes')->default(0);
            $t->timestamp('last_checked_at')->nullable();
            $t->timestamps();
        });

        Schema::create('access_points', function (Blueprint $t) {
            $t->id();
            $t->foreignId('network_interface_id')->nullable()->constrained('network_interfaces')->nullOnDelete();
            $t->string('ssid', 32);
            $t->enum('security', ['open', 'wpa2', 'wpa3', 'wpa2_wpa3'])->default('open');
            $t->text('passphrase_encrypted')->nullable();
            $t->unsignedSmallInteger('channel')->nullable();
            $t->enum('band', ['2.4', '5'])->default('2.4');
            $t->boolean('hidden')->default(false);
            $t->unsignedSmallInteger('max_clients')->default(30);
            $t->boolean('client_isolation')->default(true);
            $t->boolean('is_enabled')->default(true);
            $t->string('status', 20)->nullable();               // reported by netd
            $t->timestamps();
        });

        Schema::create('network_devices', function (Blueprint $t) {
            $t->id();
            $t->string('mac', 17)->unique();
            $t->boolean('mac_is_randomized')->default(false);   // locally-administered bit set
            $t->string('hostname', 120)->nullable();
            $t->string('device_type', 40)->nullable();
            $t->string('vendor', 120)->nullable();
            $t->string('last_ip', 45)->nullable();
            $t->enum('status', ['online', 'offline', 'blocked'])->default('offline');
            $t->timestamp('first_seen_at')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'last_seen_at']);
        });

        Schema::create('token_redemptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('access_token_id')->constrained('access_tokens')->restrictOnDelete();
            $t->foreignId('network_device_id')->constrained('network_devices')->restrictOnDelete();
            $t->string('ip_address', 45)->nullable();
            $t->string('mac', 17);
            $t->string('user_agent', 255)->nullable();
            $t->boolean('is_first_use')->default(false);
            $t->timestamp('redeemed_at');
            $t->timestamps();
            // A token is bound to a device once; reconnects reuse this row.
            $t->unique(['access_token_id', 'network_device_id']);
            $t->index('redeemed_at');
        });

        Schema::create('device_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('access_token_id')->constrained('access_tokens')->restrictOnDelete();
            $t->foreignId('network_device_id')->constrained('network_devices')->restrictOnDelete();
            $t->string('client_token', 64)->nullable();         // openNDS client token / session id
            $t->string('ip_address', 45)->nullable();
            $t->string('mac', 17);
            $t->enum('status', ['active', 'ended'])->default('active');
            // Equals network_device_id while active, NULL once ended.
            // UNIQUE => at most one live session per device (portable across MySQL/SQLite).
            $t->unsignedBigInteger('active_device_id')->nullable()->unique();
            $t->timestamp('started_at');
            $t->timestamp('last_activity_at')->nullable();
            $t->timestamp('ends_at')->nullable();               // hard stop (token expiry)
            $t->timestamp('ended_at')->nullable();
            $t->enum('end_reason', ['expired', 'exhausted', 'revoked', 'suspended', 'blocked',
                                    'admin_disconnect', 'user_logout', 'replaced', 'idle', 'system'])->nullable();
            $t->unsignedBigInteger('bytes_down')->default(0);
            $t->unsignedBigInteger('bytes_up')->default(0);
            $t->timestamps();
            $t->index(['status', 'ends_at']);
            $t->index('access_token_id');
        });

        Schema::create('blocked_devices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('network_device_id')->constrained('network_devices')->restrictOnDelete();
            $t->string('mac', 17)->index();
            $t->string('reason')->nullable();
            $t->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('blocked_at');
            $t->timestamp('unblocked_at')->nullable();
            $t->foreignId('unblocked_by')->nullable()->constrained('users')->nullOnDelete();
            // = network_device_id while the block is active; UNIQUE prevents duplicates.
            $t->unsignedBigInteger('active_device_id')->nullable()->unique();
            $t->timestamps();
        });

        Schema::create('usage_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('device_session_id')->constrained('device_sessions')->cascadeOnDelete();
            $t->timestamp('recorded_at');
            $t->unsignedBigInteger('bytes_down_delta')->default(0);
            $t->unsignedBigInteger('bytes_up_delta')->default(0);
            $t->unsignedBigInteger('rate_down_bps')->default(0);
            $t->unsignedBigInteger('rate_up_bps')->default(0);
            $t->index(['device_session_id', 'recorded_at']);
            $t->index('recorded_at');
        });

        Schema::create('network_events', function (Blueprint $t) {
            $t->id();
            $t->string('type', 60);                             // wan_down, device_blocked, service_restart ...
            $t->enum('severity', ['info', 'warning', 'error', 'critical'])->default('info');
            $t->foreignId('network_interface_id')->nullable()->constrained('network_interfaces')->nullOnDelete();
            $t->foreignId('network_device_id')->nullable()->constrained('network_devices')->nullOnDelete();
            $t->string('message', 500);
            $t->json('payload')->nullable();
            $t->timestamp('occurred_at');
            $t->timestamps();
            $t->index(['type', 'occurred_at']);
            $t->index('occurred_at');
        });

        Schema::create('system_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key', 100)->unique();
            $t->text('value')->nullable();
            $t->string('group', 40)->default('general')->index();
            $t->boolean('is_secret')->default(false);           // value stored encrypted
            $t->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 80);
            $t->string('auditable_type', 80)->nullable();
            $t->unsignedBigInteger('auditable_id')->nullable();
            $t->json('old_values')->nullable();
            $t->json('new_values')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['auditable_type', 'auditable_id']);
            $t->index('action');
            $t->index('created_at');
        });

        Schema::table('bandwidth_limits', function (Blueprint $t) {
            $t->foreign('network_device_id')->references('id')->on('network_devices')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bandwidth_limits', fn (Blueprint $t) => $t->dropForeign(['network_device_id']));
        foreach (['audit_logs', 'system_settings', 'network_events', 'usage_records', 'blocked_devices',
                  'device_sessions', 'token_redemptions', 'network_devices', 'access_points',
                  'network_interfaces'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
