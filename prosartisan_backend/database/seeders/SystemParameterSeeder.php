<?php

namespace Database\Seeders;

use App\Domain\Shared\Models\SystemParameter;
use Illuminate\Database\Seeder;

class SystemParameterSeeder extends Seeder
{
 /**
  * Run the database seeder.
  */
 public function run(): void
 {
  $parameters = [
   // Application Settings
   [
    'key' => 'app.maintenance_mode',
    'label' => 'Mode maintenance',
    'value' => '0',
    'type' => 'boolean',
    'category' => 'application',
    'description' => 'Active ou désactive le mode maintenance de l\'application',
    'is_public' => true,
    'is_editable' => true,
   ],
   [
    'key' => 'app.name',
    'label' => 'Nom de l\'application',
    'value' => 'ProSartisan',
    'type' => 'string',
    'category' => 'application',
    'description' => 'Nom affiché de l\'application',
    'is_public' => true,
    'is_editable' => true,
   ],
   [
    'key' => 'app.version',
    'label' => 'Version de l\'application',
    'value' => '1.0.0',
    'type' => 'string',
    'category' => 'application',
    'description' => 'Version actuelle de l\'application',
    'is_public' => true,
    'is_editable' => false,
   ],

   // Business Settings
   [
    'key' => 'business.commission_rate',
    'label' => 'Taux de commission',
    'value' => '5.0',
    'type' => 'percentage',
    'category' => 'business',
    'description' => 'Taux de commission prélevé sur les transactions',
    'is_public' => false,
    'is_editable' => true,
    'validation_rules' => ['min:0', 'max:100'],
   ],
   [
    'key' => 'business.min_transaction_amount',
    'label' => 'Montant minimum de transaction',
    'value' => '1000',
    'type' => 'integer',
    'category' => 'business',
    'description' => 'Montant minimum pour effectuer une transaction (en XOF)',
    'is_public' => true,
    'is_editable' => true,
    'validation_rules' => ['min:100'],
   ],
   [
    'key' => 'business.max_transaction_amount',
    'label' => 'Montant maximum de transaction',
    'value' => '5000000',
    'type' => 'integer',
    'category' => 'business',
    'description' => 'Montant maximum pour effectuer une transaction (en XOF)',
    'is_public' => true,
    'is_editable' => true,
    'validation_rules' => ['min:1000'],
   ],

   // KYC Settings
   [
    'key' => 'kyc.auto_approval_enabled',
    'label' => 'Approbation automatique KYC',
    'value' => '0',
    'type' => 'boolean',
    'category' => 'kyc',
    'description' => 'Active l\'approbation automatique des vérifications KYC',
    'is_public' => false,
    'is_editable' => true,
   ],
   [
    'key' => 'kyc.required_documents',
    'label' => 'Documents KYC requis',
    'value' => '["id_card", "proof_of_address"]',
    'type' => 'json',
    'category' => 'kyc',
    'description' => 'Liste des documents requis pour la vérification KYC',
    'is_public' => true,
    'is_editable' => true,
   ],
   [
    'key' => 'kyc.max_retry_attempts',
    'label' => 'Tentatives KYC maximum',
    'value' => '3',
    'type' => 'integer',
    'category' => 'kyc',
    'description' => 'Nombre maximum de tentatives de vérification KYC',
    'is_public' => false,
    'is_editable' => true,
    'validation_rules' => ['min:1', 'max:10'],
   ],

   // Notification Settings
   [
    'key' => 'notifications.email_enabled',
    'label' => 'Notifications email activées',
    'value' => '1',
    'type' => 'boolean',
    'category' => 'notifications',
    'description' => 'Active ou désactive les notifications par email',
    'is_public' => false,
    'is_editable' => true,
   ],
   [
    'key' => 'notifications.sms_enabled',
    'label' => 'Notifications SMS activées',
    'value' => '1',
    'type' => 'boolean',
    'category' => 'notifications',
    'description' => 'Active ou désactive les notifications par SMS',
    'is_public' => false,
    'is_editable' => true,
   ],
   [
    'key' => 'notifications.admin_email',
    'label' => 'Email administrateur',
    'value' => 'admin@prosartisan.com',
    'type' => 'email',
    'category' => 'notifications',
    'description' => 'Adresse email de l\'administrateur pour les notifications importantes',
    'is_public' => false,
    'is_editable' => true,
   ],

   // Security Settings
   [
    'key' => 'security.max_login_attempts',
    'label' => 'Tentatives de connexion maximum',
    'value' => '5',
    'type' => 'integer',
    'category' => 'security',
    'description' => 'Nombre maximum de tentatives de connexion avant blocage',
    'is_public' => false,
    'is_editable' => true,
    'validation_rules' => ['min:3', 'max:10'],
   ],
   [
    'key' => 'security.session_timeout',
    'label' => 'Timeout de session (minutes)',
    'value' => '120',
    'type' => 'integer',
    'category' => 'security',
    'description' => 'Durée d\'inactivité avant expiration de session (en minutes)',
    'is_public' => false,
    'is_editable' => true,
    'validation_rules' => ['min:15', 'max:480'],
   ],
   [
    'key' => 'security.two_factor_enabled',
    'label' => 'Authentification à deux facteurs',
    'value' => '0',
    'type' => 'boolean',
    'category' => 'security',
    'description' => 'Active l\'authentification à deux facteurs pour les administrateurs',
    'is_public' => false,
    'is_editable' => true,
   ],

   // Mobile Money Settings
   [
    'key' => 'mobile_money.orange_money_enabled',
    'label' => 'Orange Money activé',
    'value' => '1',
    'type' => 'boolean',
    'category' => 'mobile_money',
    'description' => 'Active ou désactive les paiements Orange Money',
    'is_public' => true,
    'is_editable' => true,
   ],
   [
    'key' => 'mobile_money.mtn_money_enabled',
    'label' => 'MTN Money activé',
    'value' => '1',
    'type' => 'boolean',
    'category' => 'mobile_money',
    'description' => 'Active ou désactive les paiements MTN Money',
    'is_public' => true,
    'is_editable' => true,
   ],
   [
    'key' => 'mobile_money.moov_money_enabled',
    'label' => 'Moov Money activé',
    'value' => '1',
    'type' => 'boolean',
    'category' => 'mobile_money',
    'description' => 'Active ou désactive les paiements Moov Money',
    'is_public' => true,
    'is_editable' => true,
   ],

   // Reputation Settings
   [
    'key' => 'reputation.initial_score',
    'label' => 'Score de réputation initial',
    'value' => '50',
    'type' => 'integer',
    'category' => 'reputation',
    'description' => 'Score de réputation attribué aux nouveaux artisans',
    'is_public' => true,
    'is_editable' => true,
    'validation_rules' => ['min:0', 'max:100'],
   ],
   [
    'key' => 'reputation.min_score_for_missions',
    'label' => 'Score minimum pour missions',
    'value' => '20',
    'type' => 'integer',
    'category' => 'reputation',
    'description' => 'Score minimum requis pour postuler à des missions',
    'is_public' => true,
    'is_editable' => true,
    'validation_rules' => ['min:0', 'max:100'],
   ],

   // File Upload Settings
   [
    'key' => 'uploads.max_file_size',
    'label' => 'Taille maximum de fichier (MB)',
    'value' => '10',
    'type' => 'integer',
    'category' => 'uploads',
    'description' => 'Taille maximum autorisée pour les fichiers uploadés (en MB)',
    'is_public' => true,
    'is_editable' => true,
    'validation_rules' => ['min:1', 'max:100'],
   ],
   [
    'key' => 'uploads.allowed_extensions',
    'label' => 'Extensions de fichiers autorisées',
    'value' => '["jpg", "jpeg", "png", "pdf", "doc", "docx"]',
    'type' => 'json',
    'category' => 'uploads',
    'description' => 'Liste des extensions de fichiers autorisées pour l\'upload',
    'is_public' => true,
    'is_editable' => true,
   ],

   // API Settings
   [
    'key' => 'api.rate_limit_per_minute',
    'label' => 'Limite de requêtes API par minute',
    'value' => '60',
    'type' => 'integer',
    'category' => 'api',
    'description' => 'Nombre maximum de requêtes API par minute par utilisateur',
    'is_public' => false,
    'is_editable' => true,
    'validation_rules' => ['min:10', 'max:1000'],
   ],
   [
    'key' => 'api.documentation_url',
    'label' => 'URL de la documentation API',
    'value' => 'https://api.prosartisan.com/docs',
    'type' => 'url',
    'category' => 'api',
    'description' => 'URL vers la documentation de l\'API',
    'is_public' => true,
    'is_editable' => true,
   ],
  ];

  foreach ($parameters as $parameterData) {
   SystemParameter::updateOrCreate(
    ['key' => $parameterData['key']],
    $parameterData
   );
  }
 }
}
