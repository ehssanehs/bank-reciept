<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\View\View;

class AdminDeviceController extends Controller
{
    public function index(): View
    {
        return view('admin.devices.index', ['devices' => Device::query()->orderByDesc('created_at')->paginate(25)]);
    }
}
