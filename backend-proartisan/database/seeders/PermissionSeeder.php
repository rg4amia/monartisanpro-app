<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Liste des permissions par catégorie
        $permissions = [
            // Missions
            ['name' => 'mission.create', 'category' => 'missions', 'description' => 'Créer une demande de mission'],
            ['name' => 'mission.view', 'category' => 'missions', 'description' => 'Consulter les détails des missions'],
            ['name' => 'mission.update-status', 'category' => 'missions', 'description' => 'Mettre à jour le statut des missions'],
            ['name' => 'mission.estimate', 'category' => 'missions', 'description' => 'Demander une estimation sémantique de mission'],
            ['name' => 'mission.referent-validate', 'category' => 'missions', 'description' => 'Valider physiquement une mission par un référent de zone'],

            // Devis
            ['name' => 'devis.create', 'category' => 'devis', 'description' => 'Créer un devis pour une mission'],
            ['name' => 'devis.view', 'category' => 'devis', 'description' => 'Consulter les devis'],
            ['name' => 'devis.update', 'category' => 'devis', 'description' => 'Modifier un devis'],
            ['name' => 'devis.accept', 'category' => 'devis', 'description' => 'Accepter un devis et initier le séquestre'],
            ['name' => 'devis.refuse', 'category' => 'devis', 'description' => 'Refuser un devis'],

            // Jalons
            ['name' => 'jalon.view', 'category' => 'jalons', 'description' => 'Consulter l\'état des jalons'],
            ['name' => 'jalon.submit', 'category' => 'jalons', 'description' => 'Soumettre un jalon complété pour validation'],
            ['name' => 'jalon.upload-photos', 'category' => 'jalons', 'description' => 'Uploader des photos géolocalisées sur le chantier'],
            ['name' => 'jalon.request-otp', 'category' => 'jalons', 'description' => 'Demander un code OTP pour libérer un jalon'],
            ['name' => 'jalon.validate-otp', 'category' => 'jalons', 'description' => 'Valider le jalon et libérer les fonds par OTP'],

            // J-Codes
            ['name' => 'jcode.create', 'category' => 'jcodes', 'description' => 'Générer un J-Code matériel'],
            ['name' => 'jcode.view', 'category' => 'jcodes', 'description' => 'Consulter les J-Codes'],
            ['name' => 'jcode.scan', 'category' => 'jcodes', 'description' => 'Scanner et valider un J-Code chez le quincaillier'],
            ['name' => 'jcode.upload-photo-materials', 'category' => 'jcodes', 'description' => 'Uploader la photo géolocalisée des matériaux sur chantier'],

            // E-Commerce Orders
            ['name' => 'orders.create', 'category' => 'orders', 'description' => 'Passer commande d\'articles sur le catalogue'],
            ['name' => 'orders.view', 'category' => 'orders', 'description' => 'Consulter les commandes'],
            ['name' => 'orders.manage', 'category' => 'orders', 'description' => 'Préparer et gérer le statut des commandes e-commerce'],
            ['name' => 'deliveries.manage', 'category' => 'orders', 'description' => 'Accepter et effectuer les livraisons de commande'],

            // Litiges
            ['name' => 'litige.create', 'category' => 'litiges', 'description' => 'Déclencher un signalement de litige'],
            ['name' => 'litige.view', 'category' => 'litiges', 'description' => 'Consulter le dossier d\'un litige'],
            ['name' => 'litige.arbitrate', 'category' => 'litiges', 'description' => 'Arbitrer et trancher un litige'],
            ['name' => 'litige.vote', 'category' => 'litiges', 'description' => 'Voter pour un arbitrage en jury de litige'],

            // KYC
            ['name' => 'kyc.upload', 'category' => 'kyc', 'description' => 'Uploader ses pièces justificatives KYC'],
            ['name' => 'kyc.view', 'category' => 'kyc', 'description' => 'Visualiser les dossiers KYC en attente'],
            ['name' => 'kyc.review', 'category' => 'kyc', 'description' => 'Valider ou rejeter un dossier KYC'],

            // Évaluations
            ['name' => 'evaluation.create', 'category' => 'evaluations', 'description' => 'Noter et commenter la prestation d\'un artisan'],

            // Parrainages
            ['name' => 'parrainage.create', 'category' => 'parrainages', 'description' => 'Parrainer un nouvel utilisateur'],
            ['name' => 'parrainage.view', 'category' => 'parrainages', 'description' => 'Consulter ses parrainages'],

            // Micro-crédit
            ['name' => 'micro-credit.apply', 'category' => 'micro-credit', 'description' => 'Demander un micro-crédit de trésorerie'],
            ['name' => 'micro-credit.view', 'category' => 'micro-credit', 'description' => 'Consulter ses demandes de crédit'],

            // Transactions & Wallets
            ['name' => 'transactions.view', 'category' => 'transactions', 'description' => 'Consulter l\'historique des transactions et soldes'],

            // SMS
            ['name' => 'sms.send', 'category' => 'sms', 'description' => 'Envoyer des SMS administratifs'],
            ['name' => 'sms.view', 'category' => 'sms', 'description' => 'Consulter les logs d\'envois SMS'],

            // Espace Fournisseur
            ['name' => 'supplier.dashboard', 'category' => 'supplier', 'description' => 'Accéder au tableau de bord fournisseur'],
            ['name' => 'supplier-products.manage', 'category' => 'supplier', 'description' => 'Gérer les articles de son catalogue de vente'],
        ];

        // Insertion des permissions
        foreach ($permissions as $perm) {
            Permission::updateOrCreate(['name' => $perm['name']], $perm);
        }

        // 2. Mappings par Rôle
        $mappings = [
            'client' => [
                'mission.create', 'mission.view', 'mission.estimate', 'mission.update-status',
                'devis.view', 'devis.accept', 'devis.refuse',
                'jalon.view', 'jalon.request-otp', 'jalon.validate-otp',
                'jcode.view', 'orders.create', 'orders.view',
                'litige.create', 'litige.view', 'kyc.upload',
                'evaluation.create', 'parrainage.create', 'parrainage.view',
                'transactions.view'
            ],
            'artisan' => [
                'mission.view', 'mission.update-status',
                'devis.create', 'devis.view', 'devis.update',
                'jalon.view', 'jalon.submit', 'jalon.upload-photos', 'jalon.request-otp',
                'jcode.create', 'jcode.view', 'jcode.upload-photo-materials',
                'orders.create', 'orders.view', 'litige.create', 'litige.view',
                'kyc.upload', 'parrainage.create', 'parrainage.view',
                'micro-credit.apply', 'micro-credit.view', 'transactions.view'
            ],
            'fournisseur' => [
                'jcode.scan', 'jcode.view', 'orders.view', 'orders.manage',
                'deliveries.manage', 'litige.view', 'kyc.upload',
                'transactions.view', 'supplier.dashboard', 'supplier-products.manage'
            ],
            'referent' => [
                'mission.view', 'mission.referent-validate',
                'litige.view', 'litige.arbitrate', 'litige.vote',
                'kyc.upload', 'transactions.view'
            ],
            'livreur' => [
                'orders.view', 'deliveries.manage', 'jcode.view',
                'kyc.upload', 'transactions.view', 'parrainage.create', 'parrainage.view'
            ],
            'driver' => [
                'orders.view', 'deliveries.manage', 'jcode.view',
                'kyc.upload', 'transactions.view', 'parrainage.create', 'parrainage.view'
            ],
        ];

        // Insertion des associations role <-> permission
        foreach ($mappings as $role => $permNames) {
            $rolePermIds = Permission::whereIn('name', $permNames)->pluck('id');
            
            // Nettoyer les anciennes associations
            DB::table('permission_role')->where('role', $role)->delete();

            foreach ($rolePermIds as $permId) {
                DB::table('permission_role')->insert([
                    'permission_id' => $permId,
                    'role' => $role,
                    'created_at' => now(),
                ]);
            }
        }

        $this->grantFullAccessToAdmins();
    }

    /**
     * Chantier C6/C7 — le super administrateur dispose de toutes les capacités
     * fines du backoffice via la capacité sentinelle `admin.full-access`
     * (couvre aussi les capacités ajoutées ultérieurement).
     *
     * Ignoré tant que la table pivot n'existe pas (appels précoces de ce seeder
     * depuis les migrations de 2026-07).
     */
    private function grantFullAccessToAdmins(): void
    {
        if (! Schema::hasTable('admin_permission_user')) {
            return;
        }

        $fullAccessId = Permission::where('name', 'admin.full-access')->value('id');

        if (! $fullAccessId) {
            return;
        }

        User::where('role', 'admin')->orderBy('id')->pluck('id')->each(function ($userId) use ($fullAccessId) {
            DB::table('admin_permission_user')->updateOrInsert(
                ['user_id' => $userId, 'permission_id' => $fullAccessId],
                ['created_at' => now()],
            );
        });
    }
}
