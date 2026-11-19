<?php

namespace App\Http\Controllers;

use App\Models\BandwidthLimit;
use App\Models\InternetPackage;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PackageController extends Controller
{
    public function index()
    {
        return view('packages.index', [
            'packages' => InternetPackage::with('bandwidthLimit')->withCount('tokens')
                ->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function create()
    {
        return view('packages.form', ['package' => new InternetPackage(['max_devices' => 1, 'is_active' => true, 'currency' => 'USD'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $package = DB::transaction(function () use ($data) {
            $package = InternetPackage::create($this->packageAttrs($data) + ['slug' => $this->uniqueSlug($data['name'])]);
            $this->syncBandwidth($package, $data);

            return $package;
        });

        Audit::log('package.created', $package, null, $package->toArray());

        return redirect()->route('packages.index')->with('status', "Package \"{$package->name}\" created.");
    }

    public function edit(InternetPackage $package)
    {
        $package->load('bandwidthLimit');

        return view('packages.form', ['package' => $package]);
    }

    public function update(Request $request, InternetPackage $package): RedirectResponse
    {
        $data = $this->validated($request);
        $old = $package->toArray();

        DB::transaction(function () use ($package, $data) {
            $package->update($this->packageAttrs($data));
            $this->syncBandwidth($package, $data);
        });

        Audit::log('package.updated', $package, $old, $package->fresh()->toArray());

        return redirect()->route('packages.index')->with('status', "Package \"{$package->name}\" updated.");
    }

    /** Packages referenced by tokens are retired (is_active=false) rather than deleted. */
    public function destroy(InternetPackage $package): RedirectResponse
    {
        if ($package->tokens()->exists()) {
            $package->update(['is_active' => false]);
            Audit::log('package.deactivated', $package);

            return back()->with('status', 'Package has tokens, so it was deactivated instead of deleted.');
        }

        Audit::log('package.deleted', $package, $package->toArray());
        $package->delete();

        return back()->with('status', 'Package deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:525600'],
            'data_limit_mb' => ['nullable', 'integer', 'min:1', 'max:10485760'],
            'max_devices' => ['required', 'integer', 'min:1', 'max:20'],
            'price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'download_kbps' => ['nullable', 'integer', 'min:64', 'max:10000000'],
            'upload_kbps' => ['nullable', 'integer', 'min:64', 'max:10000000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function packageAttrs(array $d): array
    {
        return [
            'name' => $d['name'],
            'description' => $d['description'] ?? null,
            'duration_minutes' => $d['duration_minutes'],
            'data_limit_mb' => $d['data_limit_mb'] ?? null,
            'max_devices' => $d['max_devices'],
            'price' => $d['price'],
            'cost_price' => $d['cost_price'] ?? 0,
            'currency' => strtoupper($d['currency']),
            'is_active' => (bool) ($d['is_active'] ?? false),
        ];
    }

    private function syncBandwidth(InternetPackage $package, array $d): void
    {
        BandwidthLimit::updateOrCreate(
            ['scope' => 'package', 'internet_package_id' => $package->id],
            ['download_kbps' => $d['download_kbps'] ?? null, 'upload_kbps' => $d['upload_kbps'] ?? null, 'is_active' => true]
        );
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'package';
        $slug = $base;
        $i = 2;
        while (InternetPackage::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
