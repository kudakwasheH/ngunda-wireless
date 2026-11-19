@extends('layouts.app')
@section('title', 'Token')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h4 mb-0">Token <code>••••••{{ $token->code_hint }}</code> <span class="badge text-bg-secondary">{{ ucfirst($token->status) }}</span></h1></div>
<div class="row g-3">
<div class="col-lg-6"><div class="card"><div class="card-body"><dl class="row mb-0">
    <dt class="col-5">Package</dt><dd class="col-7">{{ $token->package->name }}</dd>
    <dt class="col-5">Duration</dt><dd class="col-7">{{ $token->duration_minutes }} min</dd>
    <dt class="col-5">Data</dt><dd class="col-7">{{ number_format($token->data_used_bytes/1048576,1) }} MB used @if($token->data_limit_bytes) of {{ number_format($token->data_limit_bytes/1048576) }} MB @else (unlimited) @endif</dd>
    <dt class="col-5">Max devices</dt><dd class="col-7">{{ $token->max_devices }}</dd>
    <dt class="col-5">Activated</dt><dd class="col-7">{{ $token->activated_at?->format('Y-m-d H:i') ?? '—' }}</dd>
    <dt class="col-5">Expires</dt><dd class="col-7">{{ $token->expires_at?->format('Y-m-d H:i') ?? '—' }}</dd>
    @if($token->restricted_mac)<dt class="col-5">Locked to</dt><dd class="col-7"><code>{{ $token->restricted_mac }}</code></dd>@endif
    @if($token->revoked_at)<dt class="col-5">Revoked</dt><dd class="col-7">{{ $token->revoked_at->format('Y-m-d H:i') }} {{ $token->revoke_reason }}</dd>@endif
</dl></div></div></div>
@if(auth()->user()->hasPermission('tokens.revoke'))
<div class="col-lg-6"><div class="card"><div class="card-body">
    <strong>Actions</strong>
    <form method="post" action="{{ route('tokens.revoke', $token) }}" class="d-flex gap-2 mt-2" onsubmit="return confirm('Revoke this token and disconnect its devices? This cannot be undone.')">@csrf
        <input name="reason" class="form-control form-control-sm" placeholder="Reason (optional)" maxlength="200">
        <button class="btn btn-danger btn-sm" @disabled($token->status==='revoked')>Revoke</button></form>
    <div class="mt-2 d-flex gap-2">
    @if($token->status==='suspended')
        <form method="post" action="{{ route('tokens.unsuspend', $token) }}">@csrf<button class="btn btn-outline-success btn-sm">Reinstate</button></form>
    @elseif(in_array($token->status, ['unused','active']))
        <form method="post" action="{{ route('tokens.suspend', $token) }}">@csrf<button class="btn btn-outline-warning btn-sm">Suspend</button></form>
    @endif</div>
</div></div></div>
@endif
<div class="col-12"><div class="card"><div class="card-header">Redemption history</div><div class="table-responsive"><table class="table table-sm mb-0">
<thead><tr><th>When</th><th>Device (MAC)</th><th>IP</th><th>First use</th></tr></thead><tbody>
@forelse($token->redemptions as $r)<tr><td>{{ $r->redeemed_at->format('Y-m-d H:i:s') }}</td><td><code>{{ $r->mac }}</code> @if($r->device?->mac_is_randomized)<span class="badge text-bg-warning">random MAC</span>@endif</td><td>{{ $r->ip_address }}</td><td>{{ $r->is_first_use ? 'yes' : '' }}</td></tr>
@empty<tr><td colspan="4" class="text-muted text-center py-3">Not redeemed yet.</td></tr>@endforelse</tbody></table></div></div></div>
<div class="col-12"><div class="card"><div class="card-header">Sessions</div><div class="table-responsive"><table class="table table-sm mb-0">
<thead><tr><th>Started</th><th>Device</th><th>Status</th><th>Ended</th><th>Reason</th><th>↓ / ↑ (MB)</th></tr></thead><tbody>
@forelse($token->sessions as $s)<tr><td>{{ $s->started_at->format('Y-m-d H:i') }}</td><td><code>{{ $s->mac }}</code></td><td>{{ $s->status }}</td><td>{{ $s->ended_at?->format('Y-m-d H:i') }}</td><td>{{ $s->end_reason }}</td><td>{{ number_format($s->bytes_down/1048576,1) }} / {{ number_format($s->bytes_up/1048576,1) }}</td></tr>
@empty<tr><td colspan="6" class="text-muted text-center py-3">No sessions.</td></tr>@endforelse</tbody></table></div></div></div>
</div>
@endsection