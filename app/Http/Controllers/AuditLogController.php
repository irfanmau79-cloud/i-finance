<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::query()->orderBy('created_at', 'desc');

        if ($request->filled('username')) {
            $query->where('username', 'like', '%'.$request->string('username').'%');
        }

        if ($request->filled('tanggal')) {
            $query->whereDate('created_at', $request->string('tanggal'));
        }

        $logs = $query->paginate(50)->withQueryString();

        return view('audit-log.index', compact('logs'));
    }
}
