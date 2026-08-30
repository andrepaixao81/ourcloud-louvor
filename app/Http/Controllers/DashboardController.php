<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard)
    {
        $data = $request->validate([
            'from' => ['nullable','date_format:Y-m-d'],
            'to' => ['nullable','date_format:Y-m-d'],
        ]);
        try { $from = filled($data['from'] ?? null) ? Carbon::createFromFormat('Y-m-d', $data['from'])->startOfDay() : now()->startOfYear()->startOfDay(); }
        catch (\Throwable) { $from = now()->startOfYear()->startOfDay(); }
        try { $to = filled($data['to'] ?? null) ? Carbon::createFromFormat('Y-m-d', $data['to'])->endOfDay() : now()->endOfYear()->endOfDay(); }
        catch (\Throwable) { $to = now()->endOfYear()->endOfDay(); }
        return view('dashboard.index', $dashboard->summary($from,$to));
    }
}
