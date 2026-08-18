<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        if (!in_array($locale, ['en', 'fa'], true)) {
            $locale = 'en';
        }

        $request->session()->put('locale', $locale);
        app()->setLocale($locale);

        if ($user = $request->user()) {
            $user->forceFill(['locale' => $locale])->save();
        }

        return back();
    }
}
