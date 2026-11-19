<?php

namespace App\Http\Controllers;

use App\Models\AccessToken;
use App\Models\InternetPackage;
use App\Models\TokenBatch;
use App\Services\Tokens\SessionEnforcer;
use App\Services\Tokens\TokenService;
use App\Support\Audit;
use App\Support\Settings;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class TokenController extends Controller
{
    public function index(Request $request)
    {
        $q = AccessToken::with('package:id,name', 'batch:id,name')->latest('id');

        if ($request->filled('status') && in_array($request->status, AccessToken::STATUSES, true)) {
            $q->where('status', $request->status);
        }
        if ($request->filled('package')) {
            $q->where('internet_package_id', (int) $request->package);
        }
        if ($request->filled('batch')) {
            $q->where('token_batch_id', (int) $request->batch);
        }
        if ($search = trim((string) $request->get('q'))) {
            // Plain codes are never stored: search by the last 4 chars (hint) or the full code (hashed lookup).
            $q->where(function ($w) use ($search) {
                $w->where('code_hint', strtoupper($search))
                    ->orWhere('code_hash', TokenService::hash($search));
            });
        }

        return view('tokens.index', [
            'tokens' => $q->paginate(40)->withQueryString(),
            'packages' => InternetPackage::orderBy('name')->get(['id', 'name']),
            'statuses' => AccessToken::STATUSES,
        ]);
    }

    public function create()
    {
        return view('tokens.create', [
            'packages' => InternetPackage::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request, TokenService $service): RedirectResponse
    {
        $d = $request->validate([
            'internet_package_id' => ['required', 'exists:internet_packages,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'name' => ['nullable', 'string', 'max:120'],
            'is_promotional' => ['nullable', 'boolean'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
            'data_limit_mb' => ['nullable', 'integer', 'min:1', 'max:10485760'],
            'unlimited_data' => ['nullable', 'boolean'],
            'max_devices' => ['nullable', 'integer', 'min:1', 'max:20'],
            'restricted_mac' => ['nullable', 'regex:/^([0-9A-Fa-f]{2}[:-]?){5}[0-9A-Fa-f]{2}$/'],
            'valid_until' => ['nullable', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $package = InternetPackage::where('is_active', true)->findOrFail($d['internet_package_id']);

        $opts = ['created_by' => $request->user()->id, 'name' => $d['name'] ?? null,
            'is_promotional' => (bool) ($d['is_promotional'] ?? false), 'notes' => $d['notes'] ?? null,
            'restricted_mac' => $d['restricted_mac'] ?? null,
            'valid_until' => isset($d['valid_until']) ? \Illuminate\Support\Carbon::parse($d['valid_until']) : null];
        // Optional overrides => "custom package"
        foreach (['duration_minutes', 'max_devices'] as $k) {
            if (! empty($d[$k])) {
                $opts[$k] = (int) $d[$k];
            }
        }
        if (! empty($d['unlimited_data'])) {
            $opts['data_limit_mb'] = null;
        } elseif (! empty($d['data_limit_mb'])) {
            $opts['data_limit_mb'] = (int) $d['data_limit_mb'];
        }

        try {
            $result = $service->generate($package, (int) $d['quantity'], $opts);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['quantity' => $e->getMessage()]);
        }

        return redirect()->route('tokens.batches.show', $result['batch'])
            ->with('status', count($result['codes']).' token(s) generated. Codes are shown on the print sheet; they are not stored in plain text.');
    }

    public function show(AccessToken $token)
    {
        $token->load('package', 'batch', 'redemptions.device', 'sessions.device');

        return view('tokens.show', ['token' => $token]);
    }

    public function revoke(Request $request, AccessToken $token, SessionEnforcer $enforcer): RedirectResponse
    {
        $d = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);
        $enforcer->revoke($token, $request->user()->id, $d['reason'] ?? null);

        return back()->with('status', 'Token revoked and active sessions disconnected.');
    }

    public function suspend(Request $request, AccessToken $token, SessionEnforcer $enforcer): RedirectResponse
    {
        $enforcer->suspend($token, $request->input('reason'));

        return back()->with('status', 'Token suspended.');
    }

    public function unsuspend(AccessToken $token, SessionEnforcer $enforcer): RedirectResponse
    {
        $enforcer->unsuspend($token);

        return back()->with('status', 'Token reinstated.');
    }

    public function batch(TokenBatch $batch)
    {
        $batch->load('package', 'creator');

        return view('tokens.batch', [
            'batch' => $batch,
            'counts' => $batch->tokens()->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    /** Printable voucher sheet with QR codes. Only tokens still holding a recoverable code are listed. */
    public function print(TokenBatch $batch, TokenService $service)
    {
        $batch->load('package');
        $vouchers = collect($service->printableCodes($batch))->map(function ($v) {
            $url = rtrim(config('nws.portal_url'), '/').'?token='.urlencode($v['code']);
            $svg = (new Builder(writer: new SvgWriter(), data: $url, size: 140, margin: 0))->build()->getString();

            return ['token' => $v['token'], 'code' => TokenService::format($v['code']), 'qr' => $svg];
        });

        $batch->forceFill(['printed_at' => $batch->printed_at ?? now()])->save();
        Audit::log('token.batch_printed', $batch, null, ['count' => $vouchers->count()]);

        return view('tokens.print', [
            'batch' => $batch, 'vouchers' => $vouchers,
            'business' => Settings::get('business.name'), 'support' => Settings::get('business.support_phone'),
        ]);
    }
}
