<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Nincs bejelentkezve');
        }
        if (!empty($roles) && !in_array($user->jogosultsag, $roles, true)) {
            abort(403, 'Nincs jogosultsĂˇg');
        }
        return $next($request);
    }
}

