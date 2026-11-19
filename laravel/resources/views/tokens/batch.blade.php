@extends('layouts.app')
@section('title', 'Batch')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h4 mb-0">{{ $batch->name }}</h1>
    <div>@if(auth()->user()->hasPermission('tokens.print'))<a class="btn btn-primary btn-sm" target="_blank" href="{{ route('tokens.batches.print', $batch) }}"><i class="bi bi-printer"></i> Print vouchers + QR</a>@endif
    <a class="btn btn-outline-secondary btn-sm" href="{{ route('tokens.index', ['batch' => $batch->id]) }}">View tokens</a></div></div>
<div class="card"><div class="card-body">
    <dl class="row mb-0">
        <dt class="col-sm-3">Package</dt><dd class="col-sm-9">{{ $batch->package->name }}</dd>
        <dt class="col-sm-3">Quantity</dt><dd class="col-sm-9">{{ $batch->quantity }}</dd>
        <dt class="col-sm-3">Promotional</dt><dd class="col-sm-9">{{ $batch->is_promotional ? 'Yes' : 'No' }}</dd>
        <dt class="col-sm-3">Created</dt><dd class="col-sm-9">{{ $batch->created_at->format('Y-m-d H:i') }} by {{ $batch->creator?->name ?? '—' }}</dd>
        <dt class="col-sm-3">Printed</dt><dd class="col-sm-9">{{ $batch->printed_at?->format('Y-m-d H:i') ?? 'not yet' }}</dd>
        <dt class="col-sm-3">Status counts</dt><dd class="col-sm-9">@foreach($counts as $s => $c)<span class="badge text-bg-secondary">{{ $s }}: {{ $c }}</span> @endforeach</dd>
    </dl>
</div></div>
<div class="alert alert-info mt-3 small">Codes are stored as one-way hashes. A recoverable encrypted copy is kept only so unsold vouchers can be printed; it is deleted on first use and after 30 days.</div>
@endsection