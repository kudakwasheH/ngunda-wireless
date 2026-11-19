@extends('layouts.app')
@section('title', 'Dashboard')
@php
    if (! function_exists('nws_bytes')) {
        function nws_bytes($b){ $b=(float)$b; foreach(['B','KB','MB','GB','TB'] as $i=>$u){ if($b<1024||$i==4) return round($b,$i?2:0).' '.$u; $b/=1024; } }
        function nws_rate($bps){ return $bps===null ? '—' : nws_bytes($bps/8).'/s'; }
    }
@endphp
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 fw-bold mb-0">Dashboard</h1>
</div>

<h2 class="section-heading">Network overview</h2>
@unless($network['reported'])
    <div class="alert alert-warning py-2 mb-3"><i class="bi bi-exclamation-triangle-fill me-1"></i>
        The network service (<code>nws-netd</code>) has not reported yet, so live network values are shown as “—”. Nothing here is simulated.</div>
@endunless
<div class="row g-3 mb-4">
    @foreach([
        ['Starlink / Internet', $network['starlink'] ? ucfirst($network['starlink']) : '—', 'bi-globe2', 'accent-sky'],
        ['WAN interface', $network['wan_state'] ? strtoupper($network['wan_state']) : '—', 'bi-ethernet', 'accent-sky'],
        ['Connected devices', $network['reported'] ? $network['connected_devices'] : '—', 'bi-router', 'accent-teal'],
        ['Active sessions', $network['active_sessions'], 'bi-wifi', 'accent-emerald'],
        ['Blocked devices', $network['blocked_devices'], 'bi-shield-slash', 'accent-rose'],
        ['Download now', nws_rate($network['down_bps']), 'bi-arrow-down-circle', 'accent-indigo'],
        ['Upload now', nws_rate($network['up_bps']), 'bi-arrow-up-circle', 'accent-indigo'],
    ] as [$label, $value, $icon, $accent])
    <div class="col-6 col-md-4 col-xl-3"><div class="card stat-card {{ $accent }}"><div class="card-body d-flex justify-content-between align-items-center">
        <div><div class="label">{{ $label }}</div><div class="value">{{ $value }}</div></div>
        <span class="stat-icon"><i class="bi {{ $icon }}"></i></span>
    </div></div></div>
    @endforeach
</div>

<h2 class="section-heading">Business overview</h2>
<div class="row g-3 mb-4">
    @foreach([
        ['Tokens generated', $business['generated'], 'bi-ticket-perforated', 'accent-sky'],
        ['Tokens sold', $business['sold'], 'bi-bag-check', 'accent-teal'],
        ['Active tokens', $business['active'], 'bi-check2-circle', 'accent-emerald'],
        ['Expired tokens', $business['expired'], 'bi-clock-history', 'accent-amber'],
    ] as [$label, $value, $icon, $accent])
    <div class="col-6 col-md-3"><div class="card stat-card {{ $accent }}"><div class="card-body d-flex justify-content-between align-items-center">
        <div><div class="label">{{ $label }}</div><div class="value">{{ $value }}</div></div>
        <span class="stat-icon"><i class="bi {{ $icon }}"></i></span>
    </div></div></div>
    @endforeach
    @if($canFinance)
        <div class="col-6 col-md-3"><div class="card stat-card accent-emerald"><div class="card-body d-flex justify-content-between align-items-center">
            <div><div class="label">Sales today</div><div class="value">{{ number_format($business['sales_today'], 2) }}</div></div>
            <span class="stat-icon"><i class="bi bi-cash-stack"></i></span>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card stat-card accent-indigo"><div class="card-body d-flex justify-content-between align-items-center">
            <div><div class="label">Revenue this month</div><div class="value">{{ number_format($business['revenue_month'], 2) }}</div></div>
            <span class="stat-icon"><i class="bi bi-graph-up-arrow"></i></span>
        </div></div></div>
    @endif
</div>

<h2 class="section-heading">Usage analytics</h2>
<div class="row g-3">
    <div class="col-lg-8"><div class="card"><div class="card-body">
        <div class="d-flex justify-content-between mb-2"><strong>Data usage, last 7 days (MB)</strong>
            <span class="text-muted small">↓ {{ nws_bytes($usage['total_down']) }} · ↑ {{ nws_bytes($usage['total_up']) }} total</span></div>
        <canvas id="usageChart" height="110"></canvas>
    </div></div></div>
    <div class="col-lg-4"><div class="card"><div class="card-body">
        <strong>Top bandwidth consumers</strong>
        @forelse($usage['top'] as $row)
            <div class="d-flex justify-content-between border-bottom py-2 small">
                <span class="text-truncate">{{ $row->device?->hostname ?: $row->device?->mac }}<br><span class="text-muted">{{ $row->device?->last_ip }}</span></span>
                <span class="text-nowrap">{{ nws_bytes($row->total) }}</span></div>
        @empty
            <p class="text-muted small mt-2 mb-0">No usage recorded yet.</p>
        @endforelse
    </div></div></div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('vendor/chartjs/chart.umd.js') }}"></script>
<script>
const c = @json($chart);
new Chart(document.getElementById('usageChart'), {
  type: 'bar',
  data: { labels: c.labels, datasets: [
    { label: 'Download', data: c.down, backgroundColor: '#0284c7' },
    { label: 'Upload', data: c.up, backgroundColor: '#4f46e5' } ] },
  options: { responsive: true, scales: { y: { beginAtZero: true } } }
});
</script>
@endpush
