<?php
 
namespace App\Http\Middleware;
 
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
 
class SupplierOnly
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();
 
        if (! $user) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié.',
                ], Response::HTTP_UNAUTHORIZED);
            }
 
            return redirect()->route('admin.login');
        }
 
        if ($user->role !== 'fournisseur') {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé aux fournisseurs.',
                ], Response::HTTP_FORBIDDEN);
            }
 
            abort(Response::HTTP_FORBIDDEN, 'Accès réservé aux fournisseurs.');
        }
 
        return $next($request);
    }
}
