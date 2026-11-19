@extends('layouts.app')
@section('title', 'Users')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h4 mb-0">Staff users</h1>
    <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New user</a></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th></th></tr></thead><tbody>
    @foreach($users as $u)
    <tr><td>{{ $u->name }}</td><td>{{ $u->email }}</td><td>{{ $u->role?->name ?? '—' }}</td>
        <td><span class="badge text-bg-{{ $u->is_active ? 'success' : 'secondary' }}">{{ $u->is_active ? 'Active' : 'Disabled' }}</span></td>
        <td class="small text-muted">{{ $u->last_login_at?->diffForHumans() ?? 'never' }}</td>
        <td class="text-end"><a class="btn btn-outline-secondary btn-sm" href="{{ route('users.edit', $u) }}">Edit</a></td></tr>
    @endforeach
    </tbody></table></div></div>
@endsection