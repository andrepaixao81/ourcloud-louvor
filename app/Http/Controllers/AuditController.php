<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::query()
            ->with('user')
            ->where('organization_id', $request->user()->organization_id)
            ->when($request->filled('event'), fn ($q) => $q->where('event', 'like', '%'.$request->string('event')->toString().'%'))
            ->latest('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('audit.index', compact('logs'));
    }
}
