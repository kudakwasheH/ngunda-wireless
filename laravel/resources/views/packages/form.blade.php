@extends('layouts.app')
@section('title', $package->exists ? 'Edit package' : 'New package')
@section('content')
<h1 class="h4 mb-3">{{ $package->exists ? 'Edit package' : 'New package' }}</h1>
<form method="post" action="{{ $package->exists ? route('packages.update', $package) : route('packages.store') }}" class="card"><div class="card-body row g-3">
    @csrf @if($package->exists) @method('PUT') @endif
    <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ old('name', $package->name) }}" required maxlength="80"></div>
    <div class="col-md-6"><label class="form-label">Description</label><input name="description" class="form-control" value="{{ old('description', $package->description) }}" maxlength="255"></div>
    <div class="col-md-4"><label class="form-label">Duration (minutes)</label><input type="number" name="duration_minutes" class="form-control" value="{{ old('duration_minutes', $package->duration_minutes) }}" min="1" required>
        <div class="form-text">60 = 1 hour · 1440 = 1 day · 10080 = 1 week · 43200 = 30 days</div></div>
    <div class="col-md-4"><label class="form-label">Data allowance (MB)</label><input type="number" name="data_limit_mb" class="form-control" value="{{ old('data_limit_mb', $package->data_limit_mb) }}" min="1" placeholder="blank = unlimited"></div>
    <div class="col-md-4"><label class="form-label">Max devices</label><input type="number" name="max_devices" class="form-control" value="{{ old('max_devices', $package->max_devices) }}" min="1" max="20" required></div>
    <div class="col-md-4"><label class="form-label">Download limit (kbps)</label><input type="number" name="download_kbps" class="form-control" value="{{ old('download_kbps', $package->bandwidthLimit?->download_kbps) }}" min="64" placeholder="blank = no limit"></div>
    <div class="col-md-4"><label class="form-label">Upload limit (kbps)</label><input type="number" name="upload_kbps" class="form-control" value="{{ old('upload_kbps', $package->bandwidthLimit?->upload_kbps) }}" min="64" placeholder="blank = no limit"></div>
    <div class="col-md-4"></div>
    <div class="col-md-4"><label class="form-label">Price</label><input type="number" step="0.01" name="price" class="form-control" value="{{ old('price', $package->price) }}" min="0" required></div>
    <div class="col-md-4"><label class="form-label">Cost price</label><input type="number" step="0.01" name="cost_price" class="form-control" value="{{ old('cost_price', $package->cost_price) }}" min="0"></div>
    <div class="col-md-4"><label class="form-label">Currency</label><input name="currency" class="form-control text-uppercase" value="{{ old('currency', $package->currency ?: \App\Support\Settings::get('business.currency')) }}" maxlength="3" required></div>
    <div class="col-12"><div class="form-check"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" @checked(old('is_active', $package->is_active))><label class="form-check-label" for="act">Active (available for sale and portal display)</label></div></div>
    <div class="col-12"><button class="btn btn-primary">Save</button> <a href="{{ route('packages.index') }}" class="btn btn-link">Cancel</a></div>
</div></form>
@endsection