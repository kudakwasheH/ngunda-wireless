<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Vouchers · {{ $batch->name }}</title>
<style>
 body{font-family:Arial,Helvetica,sans-serif;margin:0;padding:12px;color:#111}
 .bar{margin-bottom:12px}
 .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
 .v{border:1.5px dashed #555;border-radius:6px;padding:10px;display:flex;gap:10px;align-items:center;break-inside:avoid}
 .v .t{flex:1}.brand{font-weight:700;font-size:14px;color:#0b2545}
 .code{font-family:Consolas,monospace;font-size:17px;font-weight:700;letter-spacing:1px;margin:4px 0}
 .meta{font-size:11px;color:#444}
 @media print{.bar{display:none}body{padding:0}}
</style></head><body>
<div class="bar"><button onclick="window.print()">Print</button> {{ $vouchers->count() }} voucher(s) — {{ $batch->name }}</div>
@if($vouchers->isEmpty())<p>No printable codes remain for this batch (codes are removed after first use or 30 days).</p>@endif
<div class="grid">
@foreach($vouchers as $v)
<div class="v"><div class="t"><div class="brand">{{ $business }}</div><div class="code">{{ $v['code'] }}</div>
<div class="meta">{{ $batch->package->name }} · {{ $v['token']->duration_minutes >= 1440 ? round($v['token']->duration_minutes/1440,1).' day(s)' : round($v['token']->duration_minutes/60,1).' hr' }} · {{ $v['token']->data_limit_bytes ? round($v['token']->data_limit_bytes/1073741824,2).' GB' : 'unlimited data' }}</div>
@if($support)<div class="meta">Support: {{ $support }}</div>@endif</div>
<div>{!! $v['qr'] !!}</div></div>
@endforeach
</div></body></html>