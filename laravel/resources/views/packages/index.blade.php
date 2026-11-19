@extends('layouts.app')
@section('title', 'Packages')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Internet packages</h1>
    @if(auth()->user()->hasPermission('packages.manage'))<a href="{{ route('packages.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New package</a>@endif
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Name</th><th>Duration</th><th>Data</th><th>Devices</th><th>Speed ↓/↑ (kbps)</th><th>Price</th><th>Tokens</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @foreach($packages as $p)
        <tr>
            <td><strong>{{ $p->name }}</strong><div class="small text-muted">{{ $p->description }}</div></td>
            <td>@php($m=$p->duration_minutes){{ $m % 1440 == 0 ? $m/1440 .' d' : ($m % 60 == 0 ? $m/60 .' h' : $m.' min') }}</td>
            <td>{{ $p->data_limit_mb === null ? 'Unlimited' : ($p->data_limit_mb >= 1024 ? round($p->data_limit_mb/1024, 2).' GB' : $p->data_limit_mb.' MB') }}</td>
            <td>{{ $p->max_devices }}</td>
            <td>{{ $p->bandwidthLimit?->download_kbps ?? '∞' }} / {{ $p->bandwidthLimit?->upload_kbps ?? '∞' }}</td>
            <td>{{ $p->currency }} {{ number_format($p->price, 2) }}</td>
            <td>{{ $p->tokens_count }}</td>
            <td><span class="badge text-bg-{{ $p->is_active ? 'success' : 'secondary' }}">{{ $p->is_active ? 'Active' : 'Inactive' }}</span></td>
            <td class="text-end text-nowrap">
                @if(auth()->user()->hasPermission('packages.manage'))
                <a href="{{ route('packages.edit', $p) }}" class="btn btn-outline-secondary btn-sm">Edit</a>
                <form method="post" action="{{ route('packages.destroy', $p) }}" class="d-inline" onsubmit="return confirm('Delete or deactivate this package?')">@csrf @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button></form>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody></table></div></div>
@endsection