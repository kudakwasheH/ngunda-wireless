<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $q = AuditLog::with('user:id,name')->latest('created_at')->latest('id');

        if ($action = $request->string('action')->toString()) {
            $q->where('action', 'like', '%'.addcslashes($action, '%_\\').'%');
        }
        if ($request->filled('from')) {
            $q->where('created_at', '>=', $request->date('from')->startOfDay());
        }
        if ($request->filled('to')) {
            $q->where('created_at', '<=', $request->date('to')->endOfDay());
        }

        return view('audit.index', ['logs' => $q->paginate(50)->withQueryString()]);
    }
}
