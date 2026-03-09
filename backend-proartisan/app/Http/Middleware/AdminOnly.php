<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminOnly
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if (! $user || $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux administrateurs.',
            ], 403);
        }

        return $next($request);
    }
}
