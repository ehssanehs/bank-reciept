<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale');

        if ($locale === null && ($user = Auth::user()) && in_array($user->locale, ['en', 'fa'], true)) {
            $locale = $user->locale;
        }

        $locale = in_array($locale, ['en', 'fa'], true) ? $locale : 'en';
        app()->setLocale($locale);

        return $next($request);
    }
}
