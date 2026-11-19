<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Stores configuration only. Applying network settings to the operating system is done by the
 * restricted Python service (Phase 6) after validation + backup; nothing here touches the OS.
 */
class SettingsController extends Controller
{
    public function edit()
    {
        return view('settings.edit', ['s' => Settings::all(), 'canNetwork' => request()->user()->hasPermission('network.configure')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $rules = [
            'business_name' => ['required', 'string', 'max:80'],
            'support_phone' => ['nullable', 'string', 'max:40'],
            'support_email' => ['nullable', 'email', 'max:150'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'portal_welcome' => ['nullable', 'string', 'max:300'],
            'portal_primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'token_length' => ['required', 'integer', 'min:8', 'max:24'],
            'token_max_failed_attempts' => ['required', 'integer', 'min:3', 'max:20'],
            'session_idle_timeout_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
        ];

        if ($user->hasPermission('network.configure')) {
            $rules += [
                'wifi_ssid' => ['required', 'string', 'min:1', 'max:32'],
                'wifi_security' => ['required', 'in:open,wpa2,wpa3,wpa2_wpa3'],
                'wifi_passphrase' => ['nullable', 'string', 'min:8', 'max:63'],
                'wan_interface' => ['nullable', 'regex:/^[a-zA-Z0-9_.-]{1,15}$/'],
                'lan_interface' => ['nullable', 'regex:/^[a-zA-Z0-9_.-]{1,15}$/', 'different:wan_interface'],
                'gateway_ip' => ['required', 'ipv4'],
                'dhcp_start' => ['required', 'ipv4'],
                'dhcp_end' => ['required', 'ipv4'],
                'dns_servers' => ['required', 'string', 'max:100', function ($attr, $value, $fail) {
                    foreach (array_map('trim', explode(',', $value)) as $ip) {
                        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
                            $fail('DNS servers must be a comma-separated list of valid IP addresses.');
                        }
                    }
                }],
                'global_download_kbps' => ['nullable', 'integer', 'min:64'],
                'global_upload_kbps' => ['nullable', 'integer', 'min:64'],
            ];
        }

        $d = $request->validate($rules);

        if ($user->hasPermission('network.configure')
            && ip2long($d['dhcp_start']) > ip2long($d['dhcp_end'])) {
            return back()->withErrors(['dhcp_start' => 'DHCP start must not be after DHCP end.'])->withInput();
        }

        $old = Settings::all();
        $map = [
            'business.name' => 'business_name', 'business.support_phone' => 'support_phone',
            'business.support_email' => 'support_email', 'business.terms' => 'terms', 'business.currency' => 'currency',
            'portal.welcome' => 'portal_welcome', 'portal.primary_color' => 'portal_primary_color',
            'token.length' => 'token_length', 'token.max_failed_attempts' => 'token_max_failed_attempts',
            'session.idle_timeout_minutes' => 'session_idle_timeout_minutes',
        ];
        if ($user->hasPermission('network.configure')) {
            $map += [
                'wifi.ssid' => 'wifi_ssid', 'wifi.security' => 'wifi_security',
                'network.wan_interface' => 'wan_interface', 'network.lan_interface' => 'lan_interface',
                'network.gateway_ip' => 'gateway_ip', 'network.dhcp_start' => 'dhcp_start',
                'network.dhcp_end' => 'dhcp_end', 'network.dns_servers' => 'dns_servers',
                'bandwidth.global_download_kbps' => 'global_download_kbps',
                'bandwidth.global_upload_kbps' => 'global_upload_kbps',
            ];
            if (! empty($d['wifi_passphrase'])) {
                Settings::set('wifi.passphrase', $d['wifi_passphrase'], secret: true);
            }
        }

        $new = [];
        foreach ($map as $key => $field) {
            $value = $d[$field] ?? null;
            $value = $field === 'currency' && $value ? strtoupper($value) : $value;
            Settings::set($key, $value === null ? null : (string) $value);
            $new[$key] = $value;
        }

        $changed = array_filter($new, fn ($v, $k) => ($old[$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH);
        Audit::log('settings.updated', null, array_intersect_key($old, $changed), $changed);

        return back()->with('status', 'Settings saved. Network changes are applied by the network service only after validation and backup.');
    }
}
