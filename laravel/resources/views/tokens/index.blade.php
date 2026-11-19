@extends('layouts.app')
@section('title', 'Tokens')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h4 mb-0">Access tokens</h1>
    @if(auth()->user()->hasPermission('tokens.generate'))<a href="{{ route('tokens.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Generate tokens</a>@endif</div>
<form class="row g-2 mb-3" method="get">
    <div class="col-md-3"><input name="q" class="form-control" placeholder="Full code or last 4 characters" value="{{ request('q') }}"></div>
    <div class="col-md-3"><select name="status" class="form-select"><option value="">All statuses</option>@foreach($statuses as $s)<option value="{{ $s }}" @selected(request('status')==$s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
    <div class="col-md-3"><select name="package" class="form-select"><option value="">All packages</option>@foreach($packages as $p)<option value="{{ $p->id }}" @selected(request('package')==$p->id)>{{ $p->name }}</option>@endforeach</select></div>
    <div class="col-md-3"><button class="btn btn-outline-primary w-100">Filter</button></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
<thead><tr><th>Token</th><th>Package</th><th>Batch</th><th>Status</th><th>Data used</th><th>Expires</th><th></th></tr></thead><tbody>
@php($badge=['unused'=>'secondary','active'=>'success','expired'=>'dark','suspended'=>'warning','exhausted'=>'danger','revoked'=>'danger'])
@forelse($tokens as $t)
<tr><td><code>••••••{{ $t->code_hint }}</code>@if($t->is_promotional) <span class="badge text-bg-info">promo</span>@endif @if($t->restricted_mac)<span class="badge text-bg-light" title="{{ $t->restricted_mac }}">device-locked</span>@endif</td>
<td>{{ $t->package->name }}</td><td class="small">@if($t->batch)<a href="{{ route('tokens.batches.show', $t->batch) }}">{{ $t->batch->name }}</a>@endif</td>
<td><span class="badge text-bg-{{ $badge[$t->status] }}">{{ ucfirst($t->status) }}</span></td>
<td class="small">{{ number_format($t->data_used_bytes/1048576, 1) }} MB @if($t->data_limit_bytes) / {{ number_format($t->data_limit_bytes/1048576) }} MB @endif</td>
<td class="small">{{ $t->expires_at?->format('Y-m-d H:i') ?? '—' }}</td>
<td class="text-end"><a href="{{ route('tokens.show', $t) }}" class="btn btn-outline-secondary btn-sm">Open</a></td></tr>
@empty<tr><td colspan="7" class="text-center text-muted py-4">No tokens found.</td></tr>@endforelse
</tbody></table></div></div>
<div class="mt-3">{{ $tokens->links('pagination::bootstrap-5') }}</div>
@endsection