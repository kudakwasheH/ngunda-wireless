<?php

namespace App\Http\Controllers;

use App\Models\AccessToken;
use App\Models\BlockedDevice;
use App\Models\DeviceSession;
use App\Models\NetworkDevice;
use App\Models\NetworkEvent;
use App\Models\NetworkInterface;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\UsageRecord;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard shows ONLY recorded data. When the network service (nws-netd) has not reported yet,
 * network widgets say so instead of inventing numbers.
 */
class DashboardController extends Controller
{
    public function index()
    {
        $user = request()->user();
        $data = ['canFinance' => $user->hasPermission('reports.financial') || $user->hasPermission('sales.view')];

        // ---- Network overview
        $wan = NetworkInterface::where('role', 'wan')->first();
        $lastWanEvent = NetworkEvent::whereIn('type', ['wan_up', 'wan_down'])->latest('occurred_at')->first();
        $recent = UsageRecord::where('recorded_at', '>=', now()->subSeconds(30));
        $data['network'] = [
            'reported' => $wan?->last_checked_at !== null,
            'wan_state' => $wan?->state,
            'wan_last_checked' => $wan?->last_checked_at,
            'starlink' => $lastWanEvent?->type === 'wan_up' ? 'online' : ($lastWanEvent ? 'offline' : null),
            'connected_devices' => NetworkDevice::where('status', 'online')->count(),
            'active_sessions' => DeviceSession::where('status', 'active')->count(),
            'blocked_devices' => BlockedDevice::whereNull('unblocked_at')->count(),
            'down_bps' => $recent->exists() ? (int) (clone $recent)->sum('rate_down_bps') : null,
            'up_bps' => $recent->exists() ? (int) (clone $recent)->sum('rate_up_bps') : null,
        ];

        // ---- Business overview
        $data['business'] = [
            'generated' => AccessToken::count(),
            'sold' => AccessToken::whereNotNull('sales_item_id')->count(),
            'active' => AccessToken::where('status', AccessToken::ACTIVE)->count(),
            'expired' => AccessToken::where('status', AccessToken::EXPIRED)->count(),
            'sales_today' => (float) Payment::where('status', 'paid')->whereDate('paid_at', today())->sum('amount'),
            'revenue_month' => (float) Payment::where('status', 'paid')
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
            'sales_count_today' => Sale::where('status', 'completed')->whereDate('sold_at', today())->count(),
        ];

        // ---- Usage analytics
        $data['usage'] = [
            'total_down' => (int) DeviceSession::sum('bytes_down'),
            'total_up' => (int) DeviceSession::sum('bytes_up'),
            'top' => DeviceSession::query()
                ->select('network_device_id', DB::raw('SUM(bytes_down + bytes_up) as total'),
                    DB::raw('SUM(bytes_down) as down'), DB::raw('SUM(bytes_up) as up'))
                ->with('device:id,mac,hostname,last_ip')
                ->groupBy('network_device_id')->orderByDesc('total')->limit(5)->get(),
        ];

        $daily = UsageRecord::query()
            ->where('recorded_at', '>=', now()->subDays(6)->startOfDay())
            ->select(DB::raw('DATE(recorded_at) as d'), DB::raw('SUM(bytes_down_delta) as down'), DB::raw('SUM(bytes_up_delta) as up'))
            ->groupBy('d')->orderBy('d')->get()->keyBy('d');
        $labels = $down = $up = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $labels[] = $day;
            $down[] = round(($daily[$day]->down ?? 0) / 1048576, 2);
            $up[] = round(($daily[$day]->up ?? 0) / 1048576, 2);
        }
        $data['chart'] = compact('labels', 'down', 'up');

        return view('dashboard', $data);
    }
}
