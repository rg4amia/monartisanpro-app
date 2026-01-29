<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CompletePlatformSeeder extends Seeder
{
    private array $users = [];
    private array $artisans = [];
    private array $clients = [];
    private array $fournisseurs = [];
    private array $referents = [];
    private array $missions = [];
    private array $devis = [];
    private array $chantiers = [];

    /**
     * Run the database seeds for complete platform simulation
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            $this->command->info('🚀 Starting complete platform seeding...');

            $this->seedUsers();
            $this->seedMissions();
            $this->seedDevis();
            $this->seedChantiers();
            $this->seedJalons();
            $this->seedSequestres();
            $this->seedJetonsMateriel();
            $this->seedTransactions();
            $this->seedLitiges();
            $this->seedReputationProfiles();
            $this->seedRatings();

            DB::commit();
            $this->command->info('✅ Platform seeding completed successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Seeding failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Seed users with different roles
     */
    private function seedUsers(): void
    {
        $this->command->info('👥 Seeding users...');

        // Dakar coordinates
        $dakarLocations = [
            ['lat' => 14.6937, 'lng' => -17.4441], // Plateau
            ['lat' => 14.7167, 'lng' => -17.4677], // Almadies
            ['lat' => 14.7319, 'lng' => -17.4572], // Ngor
            ['lat' => 14.7644, 'lng' => -17.3889], // Parcelles Assainies
            ['lat' => 14.7392, 'lng' => -17.4856], // Ouakam
            ['lat' => 14.6928, 'lng' => -17.4467], // Medina
            ['lat' => 14.7797, 'lng' => -17.3656], // Pikine
            ['lat' => 14.7500, 'lng' => -17.3333], // Guediawaye
        ];

        $trades = ['PLUMBER', 'ELECTRICIAN', 'MASON', 'CARPENTER', 'PAINTER', 'WELDER'];

        // Create 30 Artisans
        for ($i = 1; $i <= 30; $i++) {
            $userId = Str::uuid()->toString();
            $location = $dakarLocations[array_rand($dakarLocations)];

            DB::table('users')->insert([
                'id' => $userId,
                'name' => "Artisan {$i}",
                'email' => "artisan{$i}@prosartisan.sn",
                'phone' => "+221 77 " . str_pad($i, 7, '0', STR_PAD_LEFT),
                'password' => Hash::make('password'),
                'role' => 'ARTISAN',
                'email_verified_at' => now(),
                'created_at' => now()->subDays(rand(30, 180)),
                'updated_at' => now(),
            ]);

            $profileId = Str::uuid()->toString();
            DB::table('artisan_profiles')->insert([
                'id' => $profileId,
                'user_id' => $userId,
                'trade_category' => $trades[array_rand($trades)],
                'is_kyc_verified' => rand(0, 10) > 2, // 80% verified
                'latitude' => $location['lat'] + (rand(-100, 100) / 10000),
                'longitude' => $location['lng'] + (rand(-100, 100) / 10000),
                'created_at' => now()->subDays(rand(30, 180)),
                'updated_at' => now(),
            ]);

            $this->artisans[] = ['user_id' => $userId, 'profile_id' => $profileId];
        }

        // Create 20 Clients
        for ($i = 1; $i <= 20; $i++) {
            $userId = Str::uuid()->toString();

            DB::table('users')->insert([
                'id' => $userId,
                'name' => "Client {$i}",
                'email' => "client{$i}@prosartisan.sn",
                'phone' => "+221 76 " . str_pad($i, 7, '0', STR_PAD_LEFT),
                'password' => Hash::make('password'),
                'role' => 'CLIENT',
                'email_verified_at' => now(),
                'created_at' => now()->subDays(rand(10, 150)),
                'updated_at' => now(),
            ]);

            $this->clients[] = $userId;
        }

        // Create 10 Fournisseurs
        for ($i = 1; $i <= 10; $i++) {
            $userId = Str::uuid()->toString();
            $location = $dakarLocations[array_rand($dakarLocations)];

            DB::table('users')->insert([
                'id' => $userId,
                'name' => "Fournisseur {$i}",
                'email' => "fournisseur{$i}@prosartisan.sn",
                'phone' => "+221 78 " . str_pad($i, 7, '0', STR_PAD_LEFT),
                'password' => Hash::make('password'),
                'role' => 'FOURNISSEUR',
                'email_verified_at' => now(),
                'created_at' => now()->subDays(rand(20, 160)),
                'updated_at' => now(),
            ]);

            $profileId = Str::uuid()->toString();
            DB::table('fournisseur_profiles')->insert([
                'id' => $profileId,
                'user_id' => $userId,
                'business_name' => "Quincaillerie {$i}",
                'shop_latitude' => $location['lat'] + (rand(-100, 100) / 10000),
                'shop_longitude' => $location['lng'] + (rand(-100, 100) / 10000),
                'created_at' => now()->subDays(rand(20, 160)),
                'updated_at' => now(),
            ]);

            $this->fournisseurs[] = ['user_id' => $userId, 'profile_id' => $profileId];
        }

        // Create 5 Referent Zones
        for ($i = 1; $i <= 5; $i++) {
            $userId = Str::uuid()->toString();

            DB::table('users')->insert([
                'id' => $userId,
                'name' => "Referent Zone {$i}",
                'email' => "referent{$i}@prosartisan.sn",
                'phone' => "+221 70 " . str_pad($i, 7, '0', STR_PAD_LEFT),
                'password' => Hash::make('password'),
                'role' => 'REFERENT_ZONE',
                'email_verified_at' => now(),
                'created_at' => now()->subDays(rand(60, 200)),
                'updated_at' => now(),
            ]);

            $profileId = Str::uuid()->toString();
            DB::table('referent_zone_profiles')->insert([
                'id' => $profileId,
                'user_id' => $userId,
                'zone_name' => "Zone Dakar " . chr(64 + $i),
                'created_at' => now()->subDays(rand(60, 200)),
                'updated_at' => now(),
            ]);

            $this->referents[] = ['user_id' => $userId, 'profile_id' => $profileId];
        }

        $this->command->info("   ✓ Created " . count($this->artisans) . " artisans");
        $this->command->info("   ✓ Created " . count($this->clients) . " clients");
        $this->command->info("   ✓ Created " . count($this->fournisseurs) . " fournisseurs");
        $this->command->info("   ✓ Created " . count($this->referents) . " referents");
    }

    /**
     * Seed missions
     */
    private function seedMissions(): void
    {
        $this->command->info('📋 Seeding missions...');

        $trades = ['PLUMBER', 'ELECTRICIAN', 'MASON', 'CARPENTER', 'PAINTER', 'WELDER'];
        $statuses = ['OPEN', 'QUOTED', 'ACCEPTED', 'CANCELLED'];
        $dakarLocations = [
            ['lat' => 14.6937, 'lng' => -17.4441],
            ['lat' => 14.7167, 'lng' => -17.4677],
            ['lat' => 14.7319, 'lng' => -17.4572],
            ['lat' => 14.7644, 'lng' => -17.3889],
        ];

        $descriptions = [
            'Réparation de fuite d\'eau dans la cuisine',
            'Installation électrique complète pour nouvelle maison',
            'Construction d\'un mur de clôture',
            'Fabrication de portes et fenêtres en bois',
            'Peinture intérieure et extérieure',
            'Soudure de portail métallique',
            'Rénovation de salle de bain',
            'Installation de climatisation',
            'Carrelage de salon',
            'Plomberie sanitaire complète',
        ];

        // Create 50 missions
        for ($i = 1; $i <= 50; $i++) {
            $missionId = Str::uuid()->toString();
            $clientId = $this->clients[array_rand($this->clients)];
            $location = $dakarLocations[array_rand($dakarLocations)];
            $createdAt = now()->subDays(rand(1, 60));

            DB::table('missions')->insert([
                'id' => $missionId,
                'client_id' => $clientId,
                'description' => $descriptions[array_rand($descriptions)],
                'trade_category' => $trades[array_rand($trades)],
                'budget_min_centimes' => rand(50000, 200000) * 100, // 50k-200k XOF
                'budget_max_centimes' => rand(200000, 1000000) * 100, // 200k-1M XOF
                'status' => $statuses[array_rand($statuses)],
                'latitude' => $location['lat'] + (rand(-100, 100) / 10000),
                'longitude' => $location['lng'] + (rand(-100, 100) / 10000),
                'created_at' => $createdAt,
                'updated_at' => $createdAt->copy()->addHours(rand(1, 48)),
            ]);

            $this->missions[] = [
                'id' => $missionId,
                'client_id' => $clientId,
                'created_at' => $createdAt,
            ];
        }

        $this->command->info("   ✓ Created " . count($this->missions) . " missions");
    }

    /**
     * Seed devis (quotes)
     */
    private function seedDevis(): void
    {
        $this->command->info('💰 Seeding devis...');

        $statuses = ['PENDING', 'ACCEPTED', 'REJECTED'];

        // Create 2-5 devis per mission
        foreach ($this->missions as $mission) {
            $numDevis = rand(2, 5);

            for ($j = 0; $j < $numDevis; $j++) {
                $devisId = Str::uuid()->toString();
                $artisan = $this->artisans[array_rand($this->artisans)];
                $materialsAmount = rand(30000, 500000) * 100;
                $laborAmount = rand(50000, 300000) * 100;
                $totalAmount = $materialsAmount + $laborAmount;

                $createdAt = $mission['created_at']->copy()->addHours(rand(1, 24));
                $status = $statuses[array_rand($statuses)];

                DB::table('devis')->insert([
                    'id' => $devisId,
                    'mission_id' => $mission['id'],
                    'artisan_id' => $artisan['user_id'],
                    'total_amount_centimes' => $totalAmount,
                    'materials_amount_centimes' => $materialsAmount,
                    'labor_amount_centimes' => $laborAmount,
                    'status' => $status,
                    'expires_at' => $createdAt->copy()->addDays(7),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt->copy()->addHours(rand(1, 48)),
                ]);

                $this->devis[] = [
                    'id' => $devisId,
                    'mission_id' => $mission['id'],
                    'artisan_id' => $artisan['user_id'],
                    'status' => $status,
                    'total_amount' => $totalAmount,
                    'labor_amount' => $laborAmount,
                    'created_at' => $createdAt,
                ];
            }
        }

        $this->command->info("   ✓ Created " . count($this->devis) . " devis");
    }

    /**
     * Seed chantiers (worksites)
     */
    private function seedChantiers(): void
    {
        $this->command->info('🏗️  Seeding chantiers...');

        $statuses = ['IN_PROGRESS', 'COMPLETED', 'SUSPENDED'];

        // Create chantiers for accepted devis
        $acceptedDevis = array_filter($this->devis, fn($d) => $d['status'] === 'ACCEPTED');

        foreach (array_slice($acceptedDevis, 0, 20) as $devis) {
            $chantierId = Str::uuid()->toString();
            $mission = collect($this->missions)->firstWhere('id', $devis['mission_id']);
            $startedAt = $devis['created_at']->copy()->addDays(rand(1, 3));
            $status = $statuses[array_rand($statuses)];
            $completedAt = $status === 'COMPLETED' ? $startedAt->copy()->addDays(rand(7, 30)) : null;

            DB::table('chantiers')->insert([
                'id' => $chantierId,
                'mission_id' => $devis['mission_id'],
                'client_id' => $mission['client_id'],
                'artisan_id' => $devis['artisan_id'],
                'status' => $status,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'created_at' => $startedAt,
                'updated_at' => $completedAt ?? now(),
            ]);

            $this->chantiers[] = [
                'id' => $chantierId,
                'mission_id' => $devis['mission_id'],
                'client_id' => $mission['client_id'],
                'artisan_id' => $devis['artisan_id'],
                'status' => $status,
                'labor_amount' => $devis['labor_amount'],
                'started_at' => $startedAt,
            ];
        }

        $this->command->info("   ✓ Created " . count($this->chantiers) . " chantiers");
    }

    /**
     * Seed jalons (milestones)
     */
    private function seedJalons(): void
    {
        $this->command->info('🎯 Seeding jalons...');

        $statuses = ['PENDING', 'SUBMITTED', 'VALIDATED', 'CONTESTED', 'AUTO_VALIDATED'];

        foreach ($this->chantiers as $chantier) {
            $numJalons = rand(3, 6);
            $laborPerJalon = intval($chantier['labor_amount'] / $numJalons);

            for ($seq = 1; $seq <= $numJalons; $seq++) {
                $jalonId = Str::uuid()->toString();
                $status = $statuses[array_rand($statuses)];

                $submittedAt = null;
                $validatedAt = null;
                $autoValidationDeadline = null;
                $proofPhotoUrl = null;
                $proofLat = null;
                $proofLng = null;

                if (in_array($status, ['SUBMITTED', 'VALIDATED', 'CONTESTED', 'AUTO_VALIDATED'])) {
                    $submittedAt = $chantier['started_at']->copy()->addDays($seq * 3);
                    $autoValidationDeadline = $submittedAt->copy()->addHours(72);
                    $proofPhotoUrl = "https://storage.prosartisan.sn/proofs/jalon_{$jalonId}.jpg";
                    $proofLat = 14.6937 + (rand(-100, 100) / 10000);
                    $proofLng = -17.4441 + (rand(-100, 100) / 10000);
                }

                if (in_array($status, ['VALIDATED', 'AUTO_VALIDATED'])) {
                    $validatedAt = $submittedAt->copy()->addHours(rand(1, 48));
                }

                DB::table('jalons')->insert([
                    'id' => $jalonId,
                    'chantier_id' => $chantier['id'],
                    'description' => "Jalon {$seq}: Étape de réalisation",
                    'labor_amount_centimes' => $laborPerJalon,
                    'sequence_number' => $seq,
                    'status' => $status,
                    'proof_photo_url' => $proofPhotoUrl,
                    'proof_latitude' => $proofLat,
                    'proof_longitude' => $proofLng,
                    'proof_accuracy' => $proofPhotoUrl ? rand(5, 20) : null,
                    'proof_captured_at' => $submittedAt,
                    'proof_exif_data' => $proofPhotoUrl ? json_encode(['device' => 'iPhone 12', 'timestamp' => $submittedAt]) : null,
                    'submitted_at' => $submittedAt,
                    'validated_at' => $validatedAt,
                    'auto_validation_deadline' => $autoValidationDeadline,
                    'contest_reason' => $status === 'CONTESTED' ? 'Travail non conforme aux attentes' : null,
                    'created_at' => $chantier['started_at']->copy()->addDays(($seq - 1) * 3),
                    'updated_at' => $validatedAt ?? $submittedAt ?? now(),
                ]);
            }
        }

        $jalonCount = count($this->chantiers) * 4; // Average
        $this->command->info("   ✓ Created ~{$jalonCount} jalons");
    }

    /**
     * Seed sequestres (escrow accounts)
     */
    private function seedSequestres(): void
    {
        $this->command->info('🔒 Seeding sequestres...');

        $statuses = ['ACTIVE', 'RELEASED', 'REFUNDED'];

        foreach ($this->chantiers as $chantier) {
            $sequestreId = Str::uuid()->toString();
            $status = $statuses[array_rand($statuses)];
            $initialAmount = $chantier['labor_amount'];
            $releasedAmount = $status === 'RELEASED' ? $initialAmount : rand(0, intval($initialAmount * 0.7));
            $refundedAmount = $status === 'REFUNDED' ? $initialAmount : 0;

            DB::table('sequestres')->insert([
                'id' => $sequestreId,
                'chantier_id' => $chantier['id'],
                'client_id' => $chantier['client_id'],
                'artisan_id' => $chantier['artisan_id'],
                'initial_amount_centimes' => $initialAmount,
                'released_amount_centimes' => $releasedAmount,
                'refunded_amount_centimes' => $refundedAmount,
                'status' => $status,
                'created_at' => $chantier['started_at'],
                'updated_at' => now(),
            ]);
        }

        $this->command->info("   ✓ Created " . count($this->chantiers) . " sequestres");
    }

    /**
     * Seed jetons materiel (material tokens)
     */
    private function seedJetonsMateriel(): void
    {
        $this->command->info('🎫 Seeding jetons materiel...');

        $statuses = ['ISSUED', 'VALIDATED', 'EXPIRED', 'CANCELLED'];

        foreach ($this->chantiers as $chantier) {
            $numJetons = rand(2, 5);

            for ($j = 0; $j < $numJetons; $j++) {
                $jetonId = Str::uuid()->toString();
                $fournisseur = $this->fournisseurs[array_rand($this->fournisseurs)];
                $amount = rand(20000, 300000) * 100;
                $status = $statuses[array_rand($statuses)];
                $issuedAt = $chantier['started_at']->copy()->addDays(rand(1, 10));

                DB::table('jetons_materiel')->insert([
                    'id' => $jetonId,
                    'chantier_id' => $chantier['id'],
                    'client_id' => $chantier['client_id'],
                    'artisan_id' => $chantier['artisan_id'],
                    'fournisseur_id' => $fournisseur['user_id'],
                    'amount_centimes' => $amount,
                    'status' => $status,
                    'code' => strtoupper(Str::random(8)),
                    'expires_at' => $issuedAt->copy()->addDays(30),
                    'issued_at' => $issuedAt,
                    'validated_at' => $status === 'VALIDATED' ? $issuedAt->copy()->addDays(rand(1, 5)) : null,
                    'created_at' => $issuedAt,
                    'updated_at' => now(),
                ]);
            }
        }

        $jetonCount = count($this->chantiers) * 3; // Average
        $this->command->info("   ✓ Created ~{$jetonCount} jetons materiel");
    }

    /**
     * Seed transactions
     */
    private function seedTransactions(): void
    {
        $this->command->info('💳 Seeding transactions...');

        $types = ['DEPOSIT', 'WITHDRAWAL', 'ESCROW_RELEASE', 'REFUND', 'JETON_PURCHASE'];
        $statuses = ['PENDING', 'COMPLETED', 'FAILED', 'CANCELLED'];
        $gateways = ['WAVE', 'ORANGE_MONEY', 'MTN_MOMO', 'FREE_MONEY'];

        // Create transactions for various scenarios
        $transactionCount = 0;

        // Deposit transactions for clients
        foreach (array_slice($this->clients, 0, 15) as $clientId) {
            for ($i = 0; $i < rand(2, 5); $i++) {
                $transactionId = Str::uuid()->toString();
                $amount = rand(50000, 500000) * 100;
                $status = $statuses[array_rand($statuses)];
                $createdAt = now()->subDays(rand(1, 60));

                DB::table('transactions')->insert([
                    'id' => $transactionId,
                    'user_id' => $clientId,
                    'type' => 'DEPOSIT',
                    'amount_centimes' => $amount,
                    'status' => $status,
                    'gateway' => $gateways[array_rand($gateways)],
                    'gateway_transaction_id' => 'TXN_' . strtoupper(Str::random(12)),
                    'gateway_reference' => 'REF_' . strtoupper(Str::random(10)),
                    'metadata' => json_encode(['phone' => '+221 77 123 45 67']),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt->copy()->addMinutes(rand(1, 30)),
                ]);
                $transactionCount++;
            }
        }

        // Withdrawal transactions for artisans
        foreach (array_slice($this->artisans, 0, 20) as $artisan) {
            for ($i = 0; $i < rand(1, 3); $i++) {
                $transactionId = Str::uuid()->toString();
                $amount = rand(30000, 300000) * 100;
                $status = $statuses[array_rand($statuses)];
                $createdAt = now()->subDays(rand(1, 50));

                DB::table('transactions')->insert([
                    'id' => $transactionId,
                    'user_id' => $artisan['user_id'],
                    'type' => 'WITHDRAWAL',
                    'amount_centimes' => $amount,
                    'status' => $status,
                    'gateway' => $gateways[array_rand($gateways)],
                    'gateway_transaction_id' => 'TXN_' . strtoupper(Str::random(12)),
                    'gateway_reference' => 'REF_' . strtoupper(Str::random(10)),
                    'metadata' => json_encode(['phone' => '+221 76 987 65 43']),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt->copy()->addMinutes(rand(1, 30)),
                ]);
                $transactionCount++;
            }
        }

        $this->command->info("   ✓ Created {$transactionCount} transactions");
    }

    /**
     * Seed litiges (disputes)
     */
    private function seedLitiges(): void
    {
        $this->command->info('⚖️  Seeding litiges...');

        $types = ['QUALITY', 'PAYMENT', 'DELAY', 'OTHER'];
        $statuses = ['OPEN', 'IN_MEDIATION', 'IN_ARBITRATION', 'RESOLVED', 'CLOSED'];

        // Create disputes for some chantiers
        foreach (array_slice($this->chantiers, 0, 8) as $chantier) {
            $litigeId = Str::uuid()->toString();
            $type = $types[array_rand($types)];
            $status = $statuses[array_rand($statuses)];
            $createdAt = $chantier['started_at']->copy()->addDays(rand(5, 20));

            $mediatorId = null;
            $arbitratorId = null;
            $mediationStartedAt = null;
            $arbitrationRenderedAt = null;
            $resolvedAt = null;

            if (in_array($status, ['IN_MEDIATION', 'IN_ARBITRATION', 'RESOLVED', 'CLOSED'])) {
                $mediatorId = $this->referents[array_rand($this->referents)]['user_id'];
                $mediationStartedAt = $createdAt->copy()->addHours(rand(2, 24));
            }

            if (in_array($status, ['IN_ARBITRATION', 'RESOLVED', 'CLOSED'])) {
                $arbitratorId = $this->referents[array_rand($this->referents)]['user_id'];
                $arbitrationRenderedAt = $mediationStartedAt->copy()->addDays(rand(3, 7));
            }

            if (in_array($status, ['RESOLVED', 'CLOSED'])) {
                $resolvedAt = $arbitrationRenderedAt->copy()->addDays(rand(1, 3));
            }

            DB::table('litiges')->insert([
                'id' => $litigeId,
                'mission_id' => $chantier['mission_id'],
                'reporter_id' => rand(0, 1) ? $chantier['client_id'] : $chantier['artisan_id'],
                'defendant_id' => rand(0, 1) ? $chantier['artisan_id'] : $chantier['client_id'],
                'type' => $type,
                'description' => "Litige concernant {$type}: problème de qualité/paiement",
                'evidence' => json_encode([
                    'https://storage.prosartisan.sn/evidence/photo1.jpg',
                    'https://storage.prosartisan.sn/evidence/photo2.jpg',
                ]),
                'status' => $status,
                'mediator_id' => $mediatorId,
                'mediation_started_at' => $mediationStartedAt,
                'arbitrator_id' => $arbitratorId,
                'arbitration_decision_type' => $status === 'RESOLVED' ? 'PARTIAL_REFUND' : null,
                'arbitration_decision_amount_centimes' => $status === 'RESOLVED' ? rand(50000, 200000) * 100 : null,
                'arbitration_justification' => $status === 'RESOLVED' ? 'Décision basée sur les preuves fournies' : null,
                'arbitration_rendered_at' => $arbitrationRenderedAt,
                'resolution_outcome' => $status === 'RESOLVED' ? 'COMPROMISE' : null,
                'resolved_at' => $resolvedAt,
                'created_at' => $createdAt,
                'updated_at' => $resolvedAt ?? $arbitrationRenderedAt ?? $mediationStartedAt ?? $createdAt,
            ]);
        }

        $this->command->info("   ✓ Created 8 litiges");
    }

    /**
     * Seed reputation profiles
     */
    private function seedReputationProfiles(): void
    {
        $this->command->info('⭐ Seeding reputation profiles...');

        foreach ($this->artisans as $artisan) {
            $profileId = Str::uuid()->toString();
            $completedProjects = rand(0, 50);
            $acceptedProjects = rand($completedProjects, $completedProjects + 20);
            $currentScore = rand(0, 100);
            $averageRating = rand(30, 50) / 10; // 3.0 to 5.0

            DB::table('reputation_profiles')->insert([
                'id' => $profileId,
                'artisan_id' => $artisan['user_id'],
                'current_score' => $currentScore,
                'reliability_score' => rand(0, 100),
                'integrity_score' => rand(70, 100),
                'quality_score' => rand(0, 100),
                'reactivity_score' => rand(0, 100),
                'completed_projects' => $completedProjects,
                'accepted_projects' => $acceptedProjects,
                'average_rating' => $averageRating,
                'average_response_time_hours' => rand(1, 48),
                'fraud_attempts' => rand(0, 2),
                'last_calculated_at' => now()->subHours(rand(1, 24)),
                'created_at' => now()->subDays(rand(30, 180)),
                'updated_at' => now(),
            ]);
        }

        $this->command->info("   ✓ Created " . count($this->artisans) . " reputation profiles");
    }

    /**
     * Seed ratings
     */
    private function seedRatings(): void
    {
        $this->command->info('📊 Seeding ratings...');

        $ratingCount = 0;

        // Create ratings for completed chantiers
        $completedChantiers = array_filter($this->chantiers, fn($c) => $c['status'] === 'COMPLETED');

        foreach ($completedChantiers as $chantier) {
            // Client rates artisan
            $ratingId = Str::uuid()->toString();
            $score = rand(3, 5);
            $createdAt = now()->subDays(rand(1, 30));

            DB::table('ratings')->insert([
                'id' => $ratingId,
                'chantier_id' => $chantier['id'],
                'rater_id' => $chantier['client_id'],
                'rated_id' => $chantier['artisan_id'],
                'score' => $score,
                'comment' => $this->getRandomComment($score),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
            $ratingCount++;

            // Artisan rates client (sometimes)
            if (rand(0, 10) > 3) {
                $ratingId = Str::uuid()->toString();
                $score = rand(3, 5);

                DB::table('ratings')->insert([
                    'id' => $ratingId,
                    'chantier_id' => $chantier['id'],
                    'rater_id' => $chantier['artisan_id'],
                    'rated_id' => $chantier['client_id'],
                    'score' => $score,
                    'comment' => $this->getRandomClientComment($score),
                    'created_at' => $createdAt->copy()->addHours(rand(1, 12)),
                    'updated_at' => $createdAt->copy()->addHours(rand(1, 12)),
                ]);
                $ratingCount++;
            }
        }

        $this->command->info("   ✓ Created {$ratingCount} ratings");
    }

    /**
     * Get random comment based on score
     */
    private function getRandomComment(int $score): string
    {
        $comments = [
            5 => [
                'Excellent travail, très professionnel!',
                'Parfait, je recommande vivement!',
                'Travail impeccable et dans les délais',
                'Très satisfait du résultat final',
            ],
            4 => [
                'Bon travail dans l\'ensemble',
                'Satisfait du résultat',
                'Professionnel et ponctuel',
                'Bon rapport qualité/prix',
            ],
            3 => [
                'Travail correct',
                'Résultat acceptable',
                'Quelques petits défauts mais acceptable',
                'Moyen, pourrait être mieux',
            ],
        ];

        $scoreComments = $comments[$score] ?? $comments[3];
        return $scoreComments[array_rand($scoreComments)];
    }

    /**
     * Get random client comment based on score
     */
    private function getRandomClientComment(int $score): string
    {
        $comments = [
            5 => [
                'Client très coopératif et respectueux',
                'Excellent client, paiement rapide',
                'Communication claire et professionnelle',
            ],
            4 => [
                'Bon client, collaboration agréable',
                'Client sérieux',
                'Bonne communication',
            ],
            3 => [
                'Client correct',
                'Collaboration acceptable',
                'Quelques difficultés de communication',
            ],
        ];

        $scoreComments = $comments[$score] ?? $comments[3];
        return $scoreComments[array_rand($scoreComments)];
    }
}
