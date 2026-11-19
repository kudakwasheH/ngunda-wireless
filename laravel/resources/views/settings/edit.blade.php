@extends('layouts.app')
@section('title', 'Settings')
@section('content')
<h1 class="h4 mb-3">System settings</h1>
<form method="post" action="{{ route('settings.update') }}" autocomplete="off">@csrf @method('PUT')
<div class="card mb-3"><div class="card-header">Business &amp; captive portal</div><div class="card-body row g-3">
    <div class="col-md-6"><label class="form-label">Business name</label><input name="business_name" class="form-control" value="{{ old('business_name', $s['business.name']) }}" required></div>
    <div class="col-md-3"><label class="form-label">Currency</label><input name="currency" class="form-control text-uppercase" maxlength="3" value="{{ old('currency', $s['business.currency']) }}" required></div>
    <div class="col-md-3"><label class="form-label">Portal colour</label><input type="color" name="portal_primary_color" class="form-control form-control-color" value="{{ old('portal_primary_color', $s['portal.primary_color']) }}"></div>
    <div class="col-md-6"><label class="form-label">Support phone / WhatsApp</label><input name="support_phone" class="form-control" value="{{ old('support_phone', $s['business.support_phone']) }}"></div>
    <div class="col-md-6"><label class="form-label">Support email</label><input type="email" name="support_email" class="form-control" value="{{ old('support_email', $s['business.support_email']) }}"></div>
    <div class="col-12"><label class="form-label">Portal welcome message</label><input name="portal_welcome" class="form-control" value="{{ old('portal_welcome', $s['portal.welcome']) }}"></div>
    <div class="col-12"><label class="form-label">Terms and conditions</label><textarea name="terms" rows="3" class="form-control">{{ old('terms', $s['business.terms']) }}</textarea></div>
</div></div>
<div class="card mb-3"><div class="card-header">Token &amp; session policy</div><div class="card-body row g-3">
    <div class="col-md-4"><label class="form-label">Token length</label><input type="number" name="token_length" min="8" max="24" class="form-control" value="{{ old('token_length', $s['token.length']) }}"></div>
    <div class="col-md-4"><label class="form-label">Failed attempts before lockout</label><input type="number" name="token_max_failed_attempts" min="3" max="20" class="form-control" value="{{ old('token_max_failed_attempts', $s['token.max_failed_attempts']) }}"></div>
    <div class="col-md-4"><label class="form-label">Idle timeout (minutes)</label><input type="number" name="session_idle_timeout_minutes" min="1" class="form-control" value="{{ old('session_idle_timeout_minutes', $s['session.idle_timeout_minutes']) }}"></div>
</div></div>
@if($canNetwork)
<div class="card mb-3"><div class="card-header">Wi-Fi &amp; network <span class="badge text-bg-warning">stored only — applied by the network service after backup</span></div><div class="card-body row g-3">
    <div class="col-md-4"><label class="form-label">Wi-Fi SSID</label><input name="wifi_ssid" maxlength="32" class="form-control" value="{{ old('wifi_ssid', $s['wifi.ssid']) }}" required></div>
    <div class="col-md-4"><label class="form-label">Wi-Fi security</label><select name="wifi_security" class="form-select">
        @foreach(['open'=>'Open (portal only)','wpa2'=>'WPA2','wpa3'=>'WPA3','wpa2_wpa3'=>'WPA2/WPA3'] as $k=>$l)<option value="{{ $k }}" @selected(old('wifi_security', $s['wifi.security'])==$k)>{{ $l }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">Wi-Fi passphrase (blank = unchanged)</label><input type="password" name="wifi_passphrase" class="form-control" minlength="8" maxlength="63" autocomplete="new-password"></div>
    <div class="col-md-3"><label class="form-label">WAN interface</label><input name="wan_interface" class="form-control" value="{{ old('wan_interface', $s['network.wan_interface']) }}" placeholder="e.g. enp1s0"></div>
    <div class="col-md-3"><label class="form-label">LAN/WLAN interface</label><input name="lan_interface" class="form-control" value="{{ old('lan_interface', $s['network.lan_interface']) }}" placeholder="e.g. wlan0"></div>
    <div class="col-md-3"><label class="form-label">Gateway IP</label><input name="gateway_ip" class="form-control" value="{{ old('gateway_ip', $s['network.gateway_ip']) }}" required></div>
    <div class="col-md-3"><label class="form-label">DNS servers</label><input name="dns_servers" class="form-control" value="{{ old('dns_servers', $s['network.dns_servers']) }}" required></div>
    <div class="col-md-3"><label class="form-label">DHCP start</label><input name="dhcp_start" class="form-control" value="{{ old('dhcp_start', $s['network.dhcp_start']) }}" required></div>
    <div class="col-md-3"><label class="form-label">DHCP end</label><input name="dhcp_end" class="form-control" value="{{ old('dhcp_end', $s['network.dhcp_end']) }}" required></div>
    <div class="col-md-3"><label class="form-label">Global download limit (kbps)</label><input type="number" name="global_download_kbps" class="form-control" value="{{ old('global_download_kbps', $s['bandwidth.global_download_kbps']) }}" placeholder="blank = none"></div>
    <div class="col-md-3"><label class="form-label">Global upload limit (kbps)</label><input type="number" name="global_upload_kbps" class="form-control" value="{{ old('global_upload_kbps', $s['bandwidth.global_upload_kbps']) }}" placeholder="blank = none"></div>
</div></div>
@endif
<button class="btn btn-primary">Save settings</button>
</form>
@endsection