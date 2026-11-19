@extends('layouts.app')
@section('title', $user->exists ? 'Edit user' : 'New user')
@section('content')
<h1 class="h4 mb-3">{{ $user->exists ? 'Edit user' : 'New user' }}</h1>
<form method="post" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="card" autocomplete="off"><div class="card-body row g-3">
    @csrf @if($user->exists) @method('PUT') @endif
    <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ old('name', $user->name) }}" required></div>
    <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required></div>
    <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" value="{{ old('phone', $user->phone) }}"></div>
    <div class="col-md-6"><label class="form-label">Role</label><select name="role_id" class="form-select" required>
        @foreach($roles as $r)<option value="{{ $r->id }}" @selected(old('role_id', $user->role_id) == $r->id)>{{ $r->name }}</option>@endforeach</select></div>
    <div class="col-md-6"><label class="form-label">Password {{ $user->exists ? '(leave blank to keep)' : '' }}</label><input type="password" name="password" class="form-control" @required(! $user->exists) autocomplete="new-password"><div class="form-text">Minimum 10 characters, letters and numbers.</div></div>
    <div class="col-md-6"><label class="form-label">Confirm password</label><input type="password" name="password_confirmation" class="form-control" autocomplete="new-password"></div>
    @if($user->exists)<div class="col-12"><div class="form-check"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" @checked(old('is_active', $user->is_active))><label class="form-check-label" for="act">Account active</label></div></div>@endif
    <div class="col-12"><button class="btn btn-primary">Save</button> <a href="{{ route('users.index') }}" class="btn btn-link">Cancel</a></div>
</div></form>
@endsection