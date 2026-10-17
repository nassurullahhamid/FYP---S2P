<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(
        Request $request,
        Closure $next,
        string ...$roles
    ): Response {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Pengguna belum disahkan.');
        }

        if (! in_array($user->peranan, $roles, true)) {
            abort(403, 'Anda tidak mempunyai kebenaran untuk mengakses fungsi ini.');
        }

        return $next($request);
    }
}
