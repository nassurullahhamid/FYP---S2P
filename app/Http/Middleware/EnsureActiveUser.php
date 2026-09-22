<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->user() &&
            $request->user()->status_pengguna !== 'Aktif'
        ) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Akaun tidak aktif. Sila hubungi pentadbir.',
                ], 401);
            }

            return redirect()->route('login')->withErrors([
                'no_ic' => 'Akaun tidak aktif. Sila hubungi pentadbir.',
            ]);
        }

        return $next($request);
    }
}
