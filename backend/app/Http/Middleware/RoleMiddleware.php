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

        // Jogosultság ellenőrzés kis/nagybetű függetlenül,
        // így az "Ugyfel" és "ugyfel" is ugyanazt jelenti.
        if (!empty($roles)) {
            $userRole = strtolower((string) $user->jogosultsag);
            $allowed  = array_map(static fn ($r) => strtolower((string) $r), $roles);
            if (!in_array($userRole, $allowed, true)) {
                abort(403, 'Nincs jogosultság');
            }
        }

        return $next($request);
    }
}

