<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Domain\Identity\Models\ValueObjects\UserType;

class BackofficeAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return redirect('/backoffice/login');
        }

        $user = Auth::user();

        // Check if user is admin
        if ($user->user_type !== UserType::ADMIN->value) {
            Auth::logout();
            return redirect('/backoffice/login')->withErrors([
                'email' => 'Accès non autorisé. Seuls les administrateurs peuvent accéder au backoffice.'
            ]);
        }

        // Check if account is active
        if ($user->account_status !== 'ACTIVE') {
            Auth::logout();
            return redirect('/backoffice/login')->withErrors([
                'email' => 'Votre compte est suspendu. Contactez l\'administrateur.'
            ]);
        }

        return $next($request);
    }
}
