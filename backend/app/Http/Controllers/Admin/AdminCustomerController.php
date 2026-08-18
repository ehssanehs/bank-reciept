<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminCustomerController extends Controller
{
    public function index(Request $request): View
    {
        $customers = Customer::query()
            ->withCount('payments')
            ->when($request->filled('q'), fn ($q) => $q->where(function ($x) use ($request) {
                $term = $request->input('q');
                $x->where('name', 'like', "%$term%")
                    ->orWhere('email', 'like', "%$term%")
                    ->orWhere('phone', 'like', "%$term%")
                    ->orWhere('code', 'like', "%$term%");
            }))
            ->orderBy('created_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        return view('admin.customers.index', compact('customers'));
    }

    public function show(Customer $customer): View
    {
        $customer->load(['payments', 'telegramUsers']);

        return view('admin.customers.show', compact('customer'));
    }
}
