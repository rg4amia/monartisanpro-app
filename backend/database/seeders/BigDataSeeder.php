<?php

namespace Database\Seeders;

use App\Http\Controllers\Api\V1\ScoreController;
use App\Models\ArtisanProfile;
use App\Models\EscrowWallet;
use App\Models\LaborPayment;
use App\Models\MaterialToken;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Review;
use App\Models\Sector;
use App\Models\TokenRedemption;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Faker\Factory as Faker;

class BigDataSeeder extends Seeder
{
    private $faker;

    public function __construct()
    {
        $this->faker = Faker::create('fr_FR');
    }
    private array $firstNames = [
        'Kouassi',
        'Koné',
        'Traoré',
        'Bamba',
        'Ouattara',
        'Yao',
        'Touré',
        'Diallo',
        'Coulibaly',
        'Bah',
        'Fofana',
        'Sanogo',
        'Doumbia',
        'Ouédraogo',
        'Diabaté',
        'Camara',
        'Keita',
        'Sylla',
        'Cissé',
        'Dembélé',
        'Konaté',
        'Soumahoro',
        'Soro',
        'Silué',
        'N\'Guessan',
        'Gnabro',
        'Gbagbo',
        'Beugré',
        'Adjoumani',
        'Aké',
        'Assi',
        'Boa',
        'Dago',
        'Ehui',
        'Gnahoré',
        'Koffi',
        'Kouadio',
        'Kouamé',
        'Kouassi',
        'N\'Dri',
        'Séka',
        'Tano',
        'Yapi',
        'Zadi',
        'Zouzou'
    ];

    private array $lastNames = [
        'Adama',
        'Aminata',
        'Bakary',
        'Fatou',
        'Ibrahim',
        'Kadiatou',
        'Mamadou',
        'Mariam',
        'Moussa',
        'Nana',
        'Ousmane',
        'Ramatou',
        'Seydou',
        'Souleymane',
        'Awa',
        'Bintou',
        'Djénéba',
        'Fatoumata',
        'Hawa',
        'Issouf',
        'Jean-Claude',
        'Karim',
        'Lassina',
        'Marie',
        'Nathalie',
        'Olivier',
        'Pascal',
        'Rasmané',
        'Salif',
        'Tiémoko',
        'Valérie',
        'Youssouf',
        'Zéphirin',
        'Abdoulaye',
        'Brahima',
        'Cheick',
        'Drissa',
        'Emile',
        'François',
        'Georges',
        'Henri'
    ];

    private array $zones = [
        ['Cocody Angré', 5.3364, -4.0267],
        ['Plateau', 5.3333, -4.0333],
        ['Marcory Zone 4', 5.3500, -4.0100],
        ['Adjamé Liberté', 5.3200, -4.0500],
        ['Treichville', 5.3400, -4.0200],
        ['Yopougon Niangon', 5.3450, -4.0350],
        ['Abobo Gare', 5.3300, -4.0400],
        ['Koumassi', 5.3250, -4.0250],
        ['Cocody Riviera', 5.3380, -4.0280],
        ['Port-Bouët', 5.3420, -4.0320],
        ['Bingerville', 5.3550, -3.8950],
        ['Anyama', 5.4950, -4.0500],
        ['Songon', 5.3200, -4.2500],
        ['Attécoubé', 5.3350, -4.0550],
        ['Cocody Deux Plateaux', 5.3600, -3.9800],
        ['Yopougon Sicogi', 5.3500, -4.0800],
        ['Abobo PK18', 5.4200, -4.0200],
        ['Koumassi Remblais', 5.3100, -4.0100],
        ['Marcory Biétry', 5.3450, -4.0050],
        ['Treichville Biafra', 5.3380, -4.0180],
    ];

    private array $projectTitles = [
        'Rénovation de salon',
        'Installation électrique complète',
        'Réparation plomberie',
        'Fabrication de meubles sur mesure',
        'Construction mur de clôture',
        'Peinture complète maison',
        'Installation climatisation',
        'Réfection toiture',
        'Carrelage salle de bain',
        'Pose de faux plafond',
        'Installation cuisine équipée',
        'Rénovation façade',
        'Aménagement jardin',
        'Installation portail automatique',
        'Réparation fuite d\'eau',
        'Pose de parquet',
        'Installation système solaire',
        'Rénovation salle de bain',
        'Construction véranda',
        'Pose de carrelage',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();
        try {
            $this->command->info('🌱 Seeding BIG DATA...');

            $sectors = Sector::with('trades')->get();
            if ($sectors->isEmpty()) {
                $this->command->error('❌ No sectors found. Run SectorsTradesSeeder first!');
                return;
            }

            // Create users
            $this->command->info('Creating 100 clients...');
            $clients = $this->createClients(100);

            $this->command->info('Creating 200 artisans...');
            $artisans = $this->createArtisans(200, $sectors);

            $this->command->info('Creating 20 vendors...');
            $vendors = $this->createVendors(20);

            // Create projects
            $this->command->info('Creating 500 projects...');
            $projects = $this->createProjects(500, $clients, $artisans, $sectors);

            // Create quotes
            $this->command->info('Creating quotes for projects...');
            $this->createQuotes($projects, $artisans);

            // Create completed workflows
            $this->command->info('Creating 100 completed projects...');
            $this->createCompletedProjects(100, $clients, $artisans, $vendors, $sectors);

            // Create messages
            $this->command->info('Creating messages...');
            $this->createMessages($projects);

            DB::commit();
            $this->command->info('✅ BIG DATA seeded successfully!');
            $this->command->info("📊 Summary:");
            $this->command->info("   - Clients: 100");
            $this->command->info("   - Artisans: 200");
            $this->command->info("   - Vendors: 20");
            $this->command->info("   - Projects: 600+");
            $this->command->info("   - Quotes: 1000+");
            $this->command->info("   - Messages: 2000+");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Error seeding big data: ' . $e->getMessage());
            throw $e;
        }
    }

    private function createClients(int $count): array
    {
        $clients = [];
        $this->command->getOutput()->progressStart($count);

        for ($i = 0; $i < $count; $i++) {
            $firstName = $this->firstNames[array_rand($this->firstNames)];
            $lastName = $this->lastNames[array_rand($this->lastNames)];
            $email = strtolower($firstName . '.' . $lastName . $i . '@prosartisan.net');
            $phone = '+225070' . str_pad($i, 7, '0', STR_PAD_LEFT);

            $clients[] = User::create([
                'name' => "$firstName $lastName",
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make('password123'),
                'role' => 'client',
                'kyc_status' => ['pending', 'approved', 'approved', 'approved'][array_rand(['pending', 'approved', 'approved', 'approved'])],
                'status' => 'active',
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
            ]);

            $this->command->getOutput()->progressAdvance();
        }

        $this->command->getOutput()->progressFinish();
        return $clients;
    }

    private function createArtisans(int $count, $sectors): array
    {
        $artisans = [];
        $trades = Trade::all();
        $this->command->getOutput()->progressStart($count);

        for ($i = 0; $i < $count; $i++) {
            $firstName = $this->firstNames[array_rand($this->firstNames)];
            $lastName = $this->lastNames[array_rand($this->lastNames)];
            $email = strtolower($firstName . '.' . $lastName . $i . '@artisan.ci');
            $phone = '+225071' . str_pad($i, 7, '0', STR_PAD_LEFT);
            $zone = $this->zones[array_rand($this->zones)];

            $user = User::create([
                'name' => "$firstName $lastName",
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make('password123'),
                'role' => 'artisan',
                'kyc_status' => ['pending', 'approved', 'approved', 'approved'][array_rand(['pending', 'approved', 'approved', 'approved'])],
                'status' => 'active',
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
            ]);

            $trade = $trades->random();
            $experience = rand(1, 20);

            ArtisanProfile::create([
                'user_id' => $user->id,
                'trade_id' => $trade->id,
                'location' => new Point($zone[1] + (rand(-100, 100) / 10000), $zone[2] + (rand(-100, 100) / 10000)),
                'zone_name' => $zone[0],
                'bio' => "Artisan professionnel avec $experience ans d'expérience dans le domaine de {$trade->name}.",
                'experience_years' => $experience,
                'available' => rand(0, 10) > 2, // 80% available
            ]);

            $artisans[] = $user;
            $this->command->getOutput()->progressAdvance();
        }

        $this->command->getOutput()->progressFinish();
        return $artisans;
    }

    private function createVendors(int $count): array
    {
        $vendors = [];
        $vendorTypes = ['Quincaillerie', 'Matériaux', 'Bâti Pro', 'Électro', 'Plomberie'];

        for ($i = 0; $i < $count; $i++) {
            $type = $vendorTypes[array_rand($vendorTypes)];
            $zone = $this->zones[array_rand($this->zones)];
            $name = "$type " . $zone[0];
            $email = strtolower(str_replace(' ', '.', $name) . $i . '@vendor.ci');
            $phone = '+225072' . str_pad($i, 7, '0', STR_PAD_LEFT);

            $vendors[] = User::create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make('password123'),
                'role' => 'fournisseur',
                'kyc_status' => 'approved',
                'status' => 'active',
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
            ]);
        }

        return $vendors;
    }

    private function createProjects(int $count, array $clients, array $artisans, $sectors): array
    {
        $projects = [];
        $trades = Trade::all();
        $statuses = ['pending', 'awaiting_quotes', 'awaiting_quotes', 'awaiting_quotes', 'payment_pending', 'in_progress'];
        $this->command->getOutput()->progressStart($count);

        for ($i = 0; $i < $count; $i++) {
            $client = $clients[array_rand($clients)];
            $trade = $trades->random();
            $zone = $this->zones[array_rand($this->zones)];
            $title = $this->projectTitles[array_rand($this->projectTitles)];
            $status = $statuses[array_rand($statuses)];

            $project = Project::create([
                'client_id' => $client->id,
                'artisan_id' => in_array($status, ['payment_pending', 'in_progress']) ? $artisans[array_rand($artisans)]->id : null,
                'trade_id' => $trade->id,
                'title' => $title,
                'description' => "Travaux de {$trade->name} - " . $this->faker->sentence(10),
                'location' => new Point($zone[1] + (rand(-100, 100) / 10000), $zone[2] + (rand(-100, 100) / 10000)),
                'address' => $zone[0] . ', Abidjan',
                'budget_min' => rand(5, 50) * 10000,
                'budget_max' => rand(50, 200) * 10000,
                'status' => $status,
                'created_at' => now()->subDays(rand(0, 90)),
            ]);

            $projects[] = $project;
            $this->command->getOutput()->progressAdvance();
        }

        $this->command->getOutput()->progressFinish();
        return $projects;
    }

    private function createQuotes(array $projects, array $artisans): void
    {
        $quoteCount = 0;
        $this->command->getOutput()->progressStart(count($projects));

        foreach ($projects as $project) {
            // Skip if project already has artisan assigned
            if ($project->artisan_id) {
                $this->command->getOutput()->progressAdvance();
                continue;
            }

            // 70% of projects get quotes
            if (rand(1, 10) <= 7) {
                $numQuotes = rand(1, 5); // 1 to 5 quotes per project

                for ($i = 0; $i < $numQuotes; $i++) {
                    $artisan = $artisans[array_rand($artisans)];

                    // Check if artisan already quoted this project
                    $existingQuote = Quote::where('project_id', $project->id)
                        ->where('artisan_id', $artisan->id)
                        ->exists();

                    if ($existingQuote) continue;

                    $materialAmount = rand(10, 100) * 10000;
                    $laborAmount = rand(10, 150) * 10000;
                    $totalAmount = $materialAmount + $laborAmount;

                    $quote = Quote::create([
                        'project_id' => $project->id,
                        'artisan_id' => $artisan->id,
                        'total_amount' => $totalAmount,
                        'material_amount' => $materialAmount,
                        'labor_amount' => $laborAmount,
                        'material_percentage' => ($materialAmount / $totalAmount) * 100,
                        'labor_percentage' => ($laborAmount / $totalAmount) * 100,
                        'valid_until' => now()->addDays(rand(5, 14)),
                        'status' => ['sent', 'sent', 'sent', 'accepted', 'rejected'][array_rand(['sent', 'sent', 'sent', 'accepted', 'rejected'])],
                        'notes' => $this->faker->sentence(15),
                        'created_at' => $project->created_at->addHours(rand(1, 48)),
                    ]);

                    // Create quote items
                    $numItems = rand(2, 6);
                    for ($j = 0; $j < $numItems; $j++) {
                        $type = $j < $numItems - 1 ? 'material' : 'labor';
                        $quantity = rand(1, 100);
                        $unitPrice = rand(100, 50000);
                        $total = $quantity * $unitPrice;

                        QuoteItem::create([
                            'quote_id' => $quote->id,
                            'type' => $type,
                            'description' => $this->faker->words(3, true),
                            'quantity' => $quantity,
                            'unit' => ['m', 'm²', 'unité', 'lot', 'forfait'][array_rand(['m', 'm²', 'unité', 'lot', 'forfait'])],
                            'unit_price' => $unitPrice,
                            'total' => $total,
                        ]);
                    }

                    $quoteCount++;
                }

                // Update project status if it has quotes
                if ($project->status === 'pending') {
                    $project->update(['status' => 'awaiting_quotes']);
                }
            }

            $this->command->getOutput()->progressAdvance();
        }

        $this->command->getOutput()->progressFinish();
        $this->command->info("   Created $quoteCount quotes");
    }

    private function createCompletedProjects(int $count, array $clients, array $artisans, array $vendors, $sectors): void
    {
        $trades = Trade::all();
        $scoreController = new ScoreController();
        $this->command->getOutput()->progressStart($count);

        for ($i = 0; $i < $count; $i++) {
            $client = $clients[array_rand($clients)];
            $artisan = $artisans[array_rand($artisans)];
            $vendor = $vendors[array_rand($vendors)];
            $trade = $trades->random();
            $zone = $this->zones[array_rand($this->zones)];

            $materialAmount = rand(20, 150) * 10000;
            $laborAmount = rand(30, 200) * 10000;
            $totalAmount = $materialAmount + $laborAmount;

            $project = Project::create([
                'client_id' => $client->id,
                'artisan_id' => $artisan->id,
                'trade_id' => $trade->id,
                'title' => $this->projectTitles[array_rand($this->projectTitles)],
                'description' => "Travaux de {$trade->name} - " . $this->faker->sentence(10),
                'location' => new Point($zone[1], $zone[2]),
                'address' => $zone[0] . ', Abidjan',
                'status' => 'completed',
                'final_amount' => $totalAmount,
                'created_at' => now()->subDays(rand(30, 180)),
            ]);

            // Quote
            Quote::create([
                'project_id' => $project->id,
                'artisan_id' => $artisan->id,
                'total_amount' => $totalAmount,
                'material_amount' => $materialAmount,
                'labor_amount' => $laborAmount,
                'material_percentage' => ($materialAmount / $totalAmount) * 100,
                'labor_percentage' => ($laborAmount / $totalAmount) * 100,
                'valid_until' => now()->addDays(7),
                'status' => 'accepted',
            ]);

            // Escrow
            $escrow = EscrowWallet::create([
                'project_id' => $project->id,
                'total_amount' => $totalAmount,
                'material_wallet' => $materialAmount,
                'labor_wallet' => $laborAmount,
                'material_spent' => $materialAmount,
                'labor_released' => $laborAmount,
                'status' => 'completed',
            ]);

            // Token
            $token = MaterialToken::create([
                'code' => 'PA-' . strtoupper(substr(md5(uniqid('big' . $i, true)), 0, 6)),
                'project_id' => $project->id,
                'escrow_wallet_id' => $escrow->id,
                'vendor_id' => $vendor->id,
                'total_value' => $materialAmount,
                'remaining_value' => 0,
                'expires_at' => now()->addDays(7),
                'status' => 'fully_used',
            ]);

            TokenRedemption::create([
                'token_id' => $token->id,
                'vendor_id' => $vendor->id,
                'artisan_id' => $artisan->id,
                'amount' => $materialAmount,
                'vendor_location' => new Point($zone[1], $zone[2]),
                'artisan_location' => new Point($zone[1] + 0.0001, $zone[2] + 0.0001),
                'distance_meters' => rand(10, 50),
                'validation_method' => 'gps',
                'redeemed_at' => now()->subDays(rand(10, 60)),
            ]);

            // Milestones
            $numMilestones = rand(1, 4);
            $percentagePerMilestone = 100 / $numMilestones;

            for ($m = 0; $m < $numMilestones; $m++) {
                $milestone = Milestone::create([
                    'project_id' => $project->id,
                    'title' => "Étape " . ($m + 1),
                    'description' => $this->faker->sentence(8),
                    'labor_percentage' => $percentagePerMilestone,
                    'sequence_order' => $m + 1,
                    'status' => 'validated',
                    'requires_photo' => true,
                    'requires_otp' => true,
                    'completed_at' => now()->subDays(rand(5, 30)),
                    'validated_at' => now()->subDays(rand(4, 29)),
                ]);

                LaborPayment::create([
                    'milestone_id' => $milestone->id,
                    'artisan_id' => $artisan->id,
                    'escrow_wallet_id' => $escrow->id,
                    'amount' => ($laborAmount * $percentagePerMilestone) / 100,
                    'status' => 'completed',
                    'scheduled_at' => now()->subDays(rand(3, 28)),
                    'processed_at' => now()->subDays(rand(2, 27)),
                ]);
            }

            // Review (80% of completed projects)
            if (rand(1, 10) <= 8) {
                $rating = rand(3, 5);
                Review::create([
                    'project_id' => $project->id,
                    'artisan_id' => $artisan->id,
                    'client_id' => $client->id,
                    'rating' => $rating,
                    'comment' => $this->faker->sentence(20),
                    'quality_rating' => rand($rating - 1, 5),
                    'communication_rating' => rand($rating - 1, 5),
                    'timeliness_rating' => rand($rating - 1, 5),
                    'professionalism_rating' => rand($rating - 1, 5),
                    'would_recommend' => $rating >= 4,
                ]);
            }

            // Calculate score every 10 projects
            if ($i % 10 === 0) {
                $scoreController->calculate($artisan->id);
            }

            $this->command->getOutput()->progressAdvance();
        }

        $this->command->getOutput()->progressFinish();
    }

    private function createMessages(array $projects): void
    {
        $messageCount = 0;
        $this->command->getOutput()->progressStart(count($projects));

        foreach ($projects as $project) {
            // Only create messages for projects with artisan or quotes
            if (!$project->artisan_id && !Quote::where('project_id', $project->id)->exists()) {
                $this->command->getOutput()->progressAdvance();
                continue;
            }

            // 60% of projects have messages
            if (rand(1, 10) <= 6) {
                $numMessages = rand(2, 15);

                for ($i = 0; $i < $numMessages; $i++) {
                    $isClientSender = $i % 2 === 0;
                    $senderId = $isClientSender ? $project->client_id : ($project->artisan_id ?? Quote::where('project_id', $project->id)->first()->artisan_id);

                    ProjectMessage::create([
                        'project_id' => $project->id,
                        'sender_id' => $senderId,
                        'message' => $this->faker->sentence(rand(5, 20)),
                        'read_at' => rand(0, 1) ? now()->subDays(rand(0, 5)) : null,
                        'created_at' => $project->created_at->addHours(rand(1, 100)),
                    ]);

                    $messageCount++;
                }
            }

            $this->command->getOutput()->progressAdvance();
        }

        $this->command->getOutput()->progressFinish();
        $this->command->info("   Created $messageCount messages");
    }
}
