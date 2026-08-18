<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminReportController extends Controller
{
    public function payments(Request $request): View
    {
        $query = Payment::query()
            ->when($request->filled('date_from'), fn ($q) => $q->where('created_at', '>=', $request->input('date_from').' 00:00:00'))
            ->when($request->filled('date_to'), fn ($q) => $q->where('created_at', '<=', $request->input('date_to').' 23:59:59'))
            ->when($request->filled('bank_id'), fn ($q) => $q->whereHas('matchedTransaction', fn ($t) => $t->where('bank_id', $request->input('bank_id'))));

        $summary = (clone $query)->selectRaw('status, count(*) as total, sum(amount) as volume')
            ->groupBy('status')->pluck('total', 'status');

        $byDay = (clone $query)->selectRaw('date(created_at) as day, status, count(*) as total')
            ->groupBy('day', 'status')->orderBy('day')->limit(90)->get();

        $totalVolume = (clone $query)->sum('amount');
        $totalCount = (clone $query)->count();

        $rows = $query->with(['customer', 'matchedTransaction.bank'])->latest()->paginate(25)->withQueryString();

        return view('admin.reports.payments', compact('rows', 'summary', 'byDay', 'totalVolume', 'totalCount'));
    }
}
