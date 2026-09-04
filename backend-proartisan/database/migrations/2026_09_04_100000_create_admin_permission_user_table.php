<?php

use App\Services\Admin\AdminPermissionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier C6 (P2-10) — permissions fines du backoffice admin.
 *
 * Table pivot user <-> permission dédiée aux capacités « admin.* ».
 * Elle est distincte de `permission_role` (droits métier par rôle) : ici les
 * droits sont affectés individuellement, compte admin par compte admin, depuis
 * l'onglet « Rôles & Actions ».
 *
 * Règle de compatibilité : un admin sans aucune ligne dans cette table — ou
 * porteur de la capacité sentinelle `admin.full-access` — conserve un accès
 * total (« super admin »). Dès qu'au moins une capacité précise lui est
 * affectée, il est restreint à ce périmètre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_permission_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['user_id', 'permission_id']);
            $table->timestamp('created_at')->useCurrent();
        });

        // 1. Seed du catalogue des capacités « admin.* ».
        foreach (AdminPermissionService::catalog() as $group => $capabilities) {
            foreach ($capabilities as $name => $description) {
                DB::table('permissions')->updateOrInsert(
                    ['name' => $name],
                    [
                        'description' => $description,
                        'category' => 'admin:'.$group,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
            }
        }

        DB::table('permissions')->updateOrInsert(
            ['name' => AdminPermissionService::FULL_ACCESS],
            [
                'description' => 'Accès total au backoffice (super administrateur)',
                'category' => 'admin:core',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        // 2. Les administrateurs existants deviennent explicitement « super admin ».
        $fullAccessId = DB::table('permissions')
            ->where('name', AdminPermissionService::FULL_ACCESS)
            ->value('id');

        if ($fullAccessId) {
            DB::table('users')->where('role', 'admin')->orderBy('id')->each(function ($user) use ($fullAccessId) {
                DB::table('admin_permission_user')->updateOrInsert(
                    ['user_id' => $user->id, 'permission_id' => $fullAccessId],
                    ['created_at' => now()],
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_permission_user');

        DB::table('permissions')
            ->where('name', 'like', 'admin.%')
            ->where(fn ($q) => $q->where('category', 'like', 'admin:%')->orWhereNull('category'))
            ->delete();
    }
};
