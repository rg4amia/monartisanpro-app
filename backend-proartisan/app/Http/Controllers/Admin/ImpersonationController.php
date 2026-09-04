<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\AdminActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chantier C7 — usurpation de session (« Se connecter en tant que »).
 *
 * Le super administrateur bascule sa session web sur le compte d'un utilisateur
 * cible ; l'identifiant de l'admin d'origine est conservé en session pour
 * permettre le retour. Un bandeau permanent (app.blade.php) rappelle l'état.
 */
class ImpersonationController extends Controller
{
    public const SESSION_KEY = 'impersonator_id';

    public function __construct(private AdminActivityLogger $audit) {}

    public function start(Request $request, User $user): RedirectResponse
    {
        $admin = $request->user();

        abort_if($request->session()->has(self::SESSION_KEY), Response::HTTP_CONFLICT, 'Une usurpation est déjà en cours.');
        abort_if($user->id === $admin->id, Response::HTTP_BAD_REQUEST, 'Usurpation de votre propre compte impossible.');
        abort_if($user->role === 'admin', Response::HTTP_FORBIDDEN, "Impossible d'usurper un autre administrateur.");
        abort_if($user->trashed() || $user->anonymized_at !== null, Response::HTTP_FORBIDDEN, 'Ce compte est inactif ou anonymisé.');

        $this->audit->log('user.impersonation_started', $user, [
            'target_role' => $user->role,
        ], subjectLabel: $user->name, actor: $admin);

        Auth::guard('web')->login($user);
        $request->session()->put(self::SESSION_KEY, $admin->id);
        $request->session()->save();

        return redirect('/')->with('success', "Vous consultez désormais le compte de {$user->name}.");
    }

    public function stop(Request $request): RedirectResponse
    {
        $originalId = $request->session()->pull(self::SESSION_KEY);

        abort_if(! $originalId, Response::HTTP_BAD_REQUEST, 'Aucune usurpation en cours.');

        $admin = User::find($originalId);

        if (! $admin || $admin->role !== 'admin') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login');
        }

        $impersonated = $request->user();

        Auth::guard('web')->login($admin);
        $request->session()->save();

        $this->audit->log('user.impersonation_stopped', $impersonated, [], actor: $admin);

        return redirect()->route('admin.users')->with('success', 'Retour à votre compte administrateur.');
    }
}
