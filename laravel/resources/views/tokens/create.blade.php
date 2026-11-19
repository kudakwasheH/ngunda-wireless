@extends('layouts.app')
@section('title', 'Generate tokens')
@section('content')
<h1 class="h4 mb-3">Generate tokens</h1>
<form method="post" action="{{ route('tokens.store') }}" class="card" autocomplete="off"><div class="card-body row g-3">@csrf
    <div class="col-md-6"><label class="form-label">Package</label><select name="internet_package_id" class="form-select" required>
        @foreach($packages as $p)<option value="{{ $p->id }}" @selected(old('internet_package_id')==$p->id)>{{ $p->name }} — {{ $p->duration_minutes }} min / {{ $p->data_limit_mb ? $p->data_limit_mb.' MB' : 'unlimited' }} / {{ $p->currency }} {{ number_format($p->price,2) }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label">Quantity (1–1000)</label><input type="number" name="quantity" class="form-control" min="1" max="1000" value="{{ old('quantity', 1) }}" required></div>
    <div class="col-md-3"><label class="form-label">Batch name</label><input name="name" class="form-control" maxlength="120" value="{{ old('name') }}"></div>
    <div class="col-12"><hr><strong>Optional overrides (custom package)</strong><div class="text-muted small">Leave blank to use the package terms.</div></div>
    <div class="col-md-3"><label class="form-label">Duration (min)</label><input type="number" name="duration_minutes" class="form-control" min="1" value="{{ old('duration_minutes') }}"></div>
    <div class="col-md-3"><label class="form-label">Data (MB)</label><input type="number" name="data_limit_mb" class="form-control" min="1" value="{{ old('data_limit_mb') }}"></div>
    <div class="col-md-3"><label class="form-label">Max devices</label><input type="number" name="max_devices" class="form-control" min="1" max="20" value="{{ old('max_devices') }}"></div>
    <div class="col-md-3"><label class="form-label">Redeem before</label><input type="datetime-local" name="valid_until" class="form-control" value="{{ old('valid_until') }}"></div>
    <div class="col-md-4"><label class="form-label">Lock to device MAC (single token only)</label><input name="restricted_mac" class="form-control" placeholder="aa:bb:cc:dd:ee:ff" value="{{ old('restricted_mac') }}"></div>
    <div class="col-md-4 d-flex align-items-end gap-4">
        <div class="form-check"><input type="hidden" name="unlimited_data" value="0"><input class="form-check-input" type="checkbox" name="unlimited_data" value="1" id="ud"><label class="form-check-label" for="ud">Unlimited data</label></div>
        <div class="form-check"><input type="hidden" name="is_promotional" value="0"><input class="form-check-input" type="checkbox" name="is_promotional" value="1" id="pr"><label class="form-check-label" for="pr">Promotional</label></div></div>
    <div class="col-md-4"><label class="form-label">Notes</label><input name="notes" class="form-control" maxlength="500" value="{{ old('notes') }}"></div>
    <div class="col-12"><button class="btn btn-primary">Generate</button> <a href="{{ route('tokens.index') }}" class="btn btn-link">Cancel</a></div>
</div></form>
@endsection