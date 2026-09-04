<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\User;
use App\Services\Admin\AdminPermissionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Chantier C7 — restaure l'accès total d'un (ou tous les) administrateur(s).
 *
 * Filet de sécurité si un compte admin a été restreint par erreur depuis
 * l'onglet « Rôles & Actions » : `php artisan admin:full-access [email]`.
 */
class AdminGrantFullAccessCommand extends Command
{
    protected $signature = 'admin:full-access {email? : E-mail de l\'admin ; sinon tous les administrateurs}';

    protected $description = 'Attribue la capacité admin.full-access à un ou à tous les administrateurs.';

    public function handle(AdminPermissionService $permissions): int
    {
        $fullAccessId = Permission::where('name', AdminPermissionService::FULL_ACCESS)->value('id');

        if (! $fullAccessId) {
            $this->error('La capacité admin.full-access est absente. Lancez `php artisan migrate`.');

            return self::FAILURE;
        }

        $query = User::where('role', 'admin');

        if ($email = $this->argument('email')) {
            $query->where('email', $email);
        }

        $admins = $query->get(['id', 'name', 'email']);

        if ($admins->isEmpty()) {
            $this->warn('Aucun administrateur correspondant.');

            return self::SUCCESS;
        }

        foreach ($admins as $admin) {
            DB::table('admin_permission_user')->updateOrInsert(
                ['user_id' => $admin->id, 'permission_id' => $fullAccessId],
                ['created_at' => now()],
            );
            $permissions->forget($admin);
            $this->line("✓ {$admin->name} <{$admin->email}> — accès total restauré");
        }

        return self::SUCCESS;
    }
}
