@extends('layouts.app')
@section('title', 'Audit log')
@section('content')
<h1 class="h4 mb-3">Audit log</h1>
<form class="row g-2 mb-3" method="get">
    <div class="col-md-4"><input name="action" class="form-control" placeholder="Search action, e.g. token, auth, settings" value="{{ request('action') }}"></div>
    <div class="col-md-3"><input type="date" name="from" class="form-control" value="{{ request('from') }}"></div>
    <div class="col-md-3"><input type="date" name="to" class="form-control" value="{{ request('to') }}"></div>
    <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
    <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Subject</th><th>Details</th><th>IP</th></tr></thead><tbody>
    @forelse($logs as $l)
    <tr><td class="text-nowrap small">{{ $l->created_at->format('Y-m-d H:i:s') }}</td><td>{{ $l->user?->name ?? 'system' }}</td>
        <td><code>{{ $l->action }}</code></td><td class="small">{{ $l->auditable_type }} {{ $l->auditable_id }}</td>
        <td class="small text-break" style="max-width:420px">{{ $l->new_values ? json_encode($l->new_values) : '' }}</td><td class="small">{{ $l->ip_address }}</td></tr>
    @empty<tr><td colspan="6" class="text-center text-muted py-4">No entries.</td></tr>@endforelse
    </tbody></table></div></div>
<div class="mt-3">{{ $logs->links('pagination::bootstrap-5') }}</div>
@endsection