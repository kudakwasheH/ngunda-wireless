<?php

namespace App\Support;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Typed access to system_settings. Secret values are stored encrypted (Laravel Crypt).
 */
class Settings
{
    private const CACHE_KEY = 'nws.settings';

    /** Defaults used until an administrator saves a value. */
    public const DEFAULTS = [
        'business.name' => 'Ngunda Wireless',
        'business.support_phone' => '',
        'business.support_email' => '',
        'business.terms' => 'Access is for lawful use only. Sessions end when your time or data allowance runs out.',
        'business.currency' => 'USD',
        'portal.welcome' => 'Welcome to Ngunda Wireless. Enter your access token to connect.',
        'portal.primary_color' => '#0d6efd',
        'wifi.ssid' => 'Ngunda Wireless',
        'wifi.security' => 'open',
        'network.wan_interface' => '',
        'network.lan_interface' => '',
        'network.gateway_ip' => '10.10.0.1',
        'network.dhcp_start' => '10.10.0.50',
        'network.dhcp_end' => '10.10.3.250',
        'network.dns_servers' => '1.1.1.1,9.9.9.9',
        'token.length' => '10',
        'token.max_failed_attempts' => '5',
        'session.idle_timeout_minutes' => '30',
        'bandwidth.global_download_kbps' => '',
        'bandwidth.global_upload_kbps' => '',
    ];

    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $out = self::DEFAULTS;
            foreach (SystemSetting::all() as $row) {
                $out[$row->key] = $row->is_secret && $row->value !== null
                    ? Crypt::decryptString($row->value)
                    : $row->value;
            }

            return $out;
        });
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = self::all();

        return array_key_exists($key, $all) && $all[$key] !== null ? $all[$key] : $default;
    }

    public static function set(string $key, ?string $value, bool $secret = false): void
    {
        $group = explode('.', $key)[0];
        SystemSetting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $secret && $value !== null ? Crypt::encryptString($value) : $value,
                'group' => $group,
                'is_secret' => $secret,
            ]
        );
        Cache::forget(self::CACHE_KEY);
    }
}
