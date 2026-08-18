<?php

namespace App\Http\Middleware;

use App\Models\Device;
use App\Services\Auth\DeviceAuthenticator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeviceAuthenticated
{
    public function __construct(private readonly DeviceAuthenticator $authenticator) {}

    public function handle(Request $request, Closure $next): Response
    {
        $device = $this->authenticator->authenticate($request);

        $request->attributes->set(DeviceAuthenticator::ATTR_DEVICE, $device);

        return $next($request);
    }
}
