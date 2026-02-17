<?php

namespace Database\Seeders;

use App\Http\Controllers\Api\V1\ScoreController;
use App\Models\ArtisanProfile;
use App\Models\EscrowWallet;
use App\Models\LaborPayment;
use App\Models\MaterialToken;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Review;
use App\Models\Sector;
use App\Models\TokenRedemption;
use App\Models\Trade;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use MatanYadaev\EloquentSpatial\Objects\Point;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();
        try {
            $this->command->info('🌱 Seeding test data...');

            // Get sectors and trades
            $sectors = Sector::with('trades')->get();
            if ($sectors->isEmpty()) {
                $this->command->error('❌ No sectors found. Run SectorsTradesSeeder first!');
                return;
            }

            // Create test users
            $this->command->info('Creating users...');
            $users = $this->createUsers($sectors);

            // Create projects with various statuses
            $this->command->info('Creating projects...');
            $projects = $this->createProjects($users);

            // Create quotes
            $this->command->info('Creating quotes...');
            $this->createQuotes($projects, $users);

            // Create completed project workflows
            $this->command->info('Creating completed project workflows...');
            $this->createCompletedWorkflows($users);

            DB::commit();
            $this->command->info('✅ Test data seeded successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Error seeding test data: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create test users with different roles
     */
    protected function createUsers($sectors): array
    {
        $users = [];

        // Clients (5)
        $clientNames = [
            ['Kouassi', 'Yao', 'kouassi.yao@email.ci', '+2250701234567'],
            ['Koné', 'Aminata', 'kone.aminata@email.ci', '+2250702345678'],
            ['Traoré', 'Seydou', 'traore.seydou@email.ci', '+2250703456789'],
            ['Bamba', 'Marie', 'bamba.marie@email.ci', '+2250704567890'],
            ['Ouattara', 'Ibrahim', 'ouattara.ibrahim@email.ci', '+2250705678901'],
        ];

        foreach ($clientNames as [$firstName, $lastName, $email, $phone]) {
            $users['clients'][] = User::create([
                'name' => "$firstName $lastName",
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make('password123'),
                'role' => 'client',
                'kyc_status' => 'approved',
                'status' => 'active',
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
            ]);
        }

        // Artisans (10) - various trades
        $artisanData = [
            ['Koné', 'Adama', 'kone.adama@artisan.ci', '+2250711234567', 'Maçon', [5.3364, -4.0267], 'Cocody, Abidjan', 5],
            ['Yao', 'Jean-Claude', 'yao.jeanclaude@artisan.ci', '+2250712345678', 'Électricien', [5.3333, -4.0333], 'Plateau, Abidjan', 8],
            ['Touré', 'Mamadou', 'toure.mamadou@artisan.ci', '+2250713456789', 'Plombier', [5.3500, -4.0100], 'Marcory, Abidjan', 6],
            ['Diallo', 'Abdoulaye', 'diallo.abdoulaye@artisan.ci', '+2250714567890', 'Menuisier', [5.3200, -4.0500], 'Adjamé, Abidjan', 10],
            ['Coulibaly', 'Moussa', 'coulibaly.moussa@artisan.ci', '+2250715678901', 'Peintre', [5.3400, -4.0200], 'Treichville, Abidjan', 4],
            ['Bah', 'Ibrahima', 'bah.ibrahima@artisan.ci', '+2250716789012', 'Carreleur', [5.3450, -4.0350], 'Yopougon, Abidjan', 7],
            ['Fofana', 'Lassina', 'fofana.lassina@artisan.ci', '+2250717890123', 'Soudeur', [5.3300, -4.0400], 'Abobo, Abidjan', 9],
            ['Sanogo', 'Souleymane', 'sanogo.souleymane@artisan.ci', '+2250718901234', 'Mécanicien Auto', [5.3250, -4.0250], 'Koumassi, Abidjan', 12],
            ['Doumbia', 'Bakary', 'doumbia.bakary@artisan.ci', '+2250719012345', 'Climaticien', [5.3380, -4.0280], 'Cocody II, Abidjan', 5],
            ['Ouédraogo', 'Rasmané', 'ouedraogo.rasmane@artisan.ci', '+2250710123456', 'Jardinier', [5.3420, -4.0320], 'Riviera, Abidjan', 3],
        ];

        foreach ($artisanData as [$firstName, $lastName, $email, $phone, $tradeName, $coords, $zone, $experience]) {
            $user = User::create([
                'name' => "$firstName $lastName",
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make('password123'),
                'role' => 'artisan',
                'kyc_status' => 'approved',
                'status' => 'active',
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
            ]);

            // Find trade
            $trade = Trade::whereHas('sector', function ($q) {
                // Bâtiment sector
            })->where('name', 'like', "%$tradeName%")->first();

            if (!$trade) {
                // Fallback to first trade
                $trade = $sectors->first()->trades->first();
            }

            ArtisanProfile::create([
                'user_id' => $user->id,
                'trade_id' => $trade->id,
                'location' => new Point($coords[0], $coords[1]),
                'zone_name' => $zone,
                'bio' => "Artisan professionnel avec $experience ans d'expérience. Travail soigné et respect des délais.",
                'experience_years' => $experience,
                'available' => true,
            ]);

            $users['artisans'][] = $user;
        }

        // Vendors (3)
        $vendorData = [
            ['Quincaillerie Yopougon', 'quincaillerie.yopougon@vendor.ci', '+2250721234567', [5.3450, -4.0350]],
            ['Matériaux du Centre', 'materiaux.centre@vendor.ci', '+2250722345678', [5.3333, -4.0333]],
            ['Bâti Pro Abidjan', 'batipro.abidjan@vendor.ci', '+2250723456789', [5.3364, -4.0267]],
        ];

        foreach ($vendorData as [$name, $email, $phone, $coords]) {
            $user = User::create([
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

            $users['vendors'][] = $user;
        }

        return $users;
    }

    /**
     * Create projects with various statuses
     */
    protected function createProjects($users): array
    {
        $projects = [];

        // Get some trades for projects
        $peintureTrade = Trade::where('name', 'like', '%Peintre%')->first();
        $electricienTrade = Trade::where('name', 'like', '%Électricien%')->first();
        $plombierTrade = Trade::where('name', 'like', '%Plombier%')->first();
        $menuisierTrade = Trade::where('name', 'like', '%Menuisier%')->first();
        $maconTrade = Trade::where('name', 'like', '%Maçon%')->first();

        // Fallback to first trade if specific ones not found
        $defaultTrade = Trade::first();

        $projectData = [
            [
                'client' => $users['clients'][0],
                'trade' => $peintureTrade ?? $defaultTrade,
                'title' => 'Rénovation de salon',
                'description' => 'Travaux de peinture et pose de carrelage dans le salon (25m²)',
                'coords' => [5.3364, -4.0267],
                'address' => 'Cocody Angré 8ème Tranche, Abidjan',
                'status' => 'pending',
            ],
            [
                'client' => $users['clients'][1],
                'trade' => $electricienTrade ?? $defaultTrade,
                'title' => 'Installation électrique complète',
                'description' => 'Installation électrique pour une nouvelle maison (4 chambres)',
                'coords' => [5.3500, -4.0100],
                'address' => 'Marcory Zone 4, Abidjan',
                'status' => 'awaiting_quotes',
            ],
            [
                'client' => $users['clients'][2],
                'trade' => $plombierTrade ?? $defaultTrade,
                'title' => 'Réparation plomberie',
                'description' => 'Fuite d\'eau dans la salle de bain, remplacement de tuyaux',
                'coords' => [5.3200, -4.0500],
                'address' => 'Adjamé Liberté, Abidjan',
                'status' => 'awaiting_quotes',
            ],
            [
                'client' => $users['clients'][3],
                'trade' => $menuisierTrade ?? $defaultTrade,
                'title' => 'Fabrication de meubles sur mesure',
                'description' => 'Bibliothèque en bois massif (2m x 3m) + bureau',
                'coords' => [5.3400, -4.0200],
                'address' => 'Treichville Biafra, Abidjan',
                'status' => 'awaiting_quotes',
            ],
            [
                'client' => $users['clients'][4],
                'trade' => $maconTrade ?? $defaultTrade,
                'title' => 'Construction mur de clôture',
                'description' => 'Construction d\'un mur de clôture en parpaings (50m linéaire, hauteur 2m)',
                'coords' => [5.3450, -4.0350],
                'address' => 'Yopougon Niangon, Abidjan',
                'status' => 'pending',
            ],
        ];

        foreach ($projectData as $data) {
            $project = Project::create([
                'client_id' => $data['client']->id,
                'trade_id' => $data['trade']->id,
                'title' => $data['title'],
                'description' => $data['description'],
                'location' => new Point($data['coords'][0], $data['coords'][1]),
                'address' => $data['address'],
                'status' => $data['status'],
            ]);

            $projects[] = $project;
        }

        return $projects;
    }

    /**
     * Create quotes for projects
     */
    protected function createQuotes($projects, $users): void
    {
        // Project 1: Rénovation de salon - 1 quote (pending)
        $quote1 = Quote::create([
            'project_id' => $projects[1]->id, // Installation électrique
            'artisan_id' => $users['artisans'][1]->id, // Jean-Claude (Électricien)
            'total_amount' => 850000, // 850,000 XOF
            'material_amount' => 400000,
            'labor_amount' => 450000,
            'material_percentage' => 47.06,
            'labor_percentage' => 52.94,
            'valid_until' => now()->addDays(7),
            'status' => 'sent',
            'notes' => 'Matériel de qualité conforme aux normes CI. Garantie 2 ans.',
        ]);

        QuoteItem::create([
            'quote_id' => $quote1->id,
            'item_type' => 'material',
            'description' => 'Câbles électriques (100m)',
            'quantity' => 100,
            'unit' => 'm',
            'unit_price' => 1500,
            'total' => 150000,
        ]);

        QuoteItem::create([
            'quote_id' => $quote1->id,
            'item_type' => 'material',
            'description' => 'Disjoncteurs et tableaux',
            'quantity' => 1,
            'unit' => 'lot',
            'unit_price' => 180000,
            'total' => 180000,
        ]);

        QuoteItem::create([
            'quote_id' => $quote1->id,
            'item_type' => 'material',
            'description' => 'Prises et interrupteurs',
            'quantity' => 30,
            'unit' => 'unité',
            'unit_price' => 2333,
            'total' => 70000,
        ]);

        QuoteItem::create([
            'quote_id' => $quote1->id,
            'item_type' => 'labor',
            'description' => 'Installation électrique complète',
            'quantity' => 1,
            'unit' => 'forfait',
            'unit_price' => 450000,
            'total' => 450000,
        ]);

        // Project 2: Réparation plomberie - 2 quotes
        $quote2 = Quote::create([
            'project_id' => $projects[2]->id,
            'artisan_id' => $users['artisans'][2]->id, // Mamadou (Plombier)
            'total_amount' => 125000,
            'material_amount' => 45000,
            'labor_amount' => 80000,
            'material_percentage' => 36.00,
            'labor_percentage' => 64.00,
            'valid_until' => now()->addDays(5),
            'status' => 'sent',
        ]);

        QuoteItem::create([
            'quote_id' => $quote2->id,
            'item_type' => 'material',
            'description' => 'Tuyaux PVC (10m)',
            'quantity' => 10,
            'unit' => 'm',
            'unit_price' => 2500,
            'total' => 25000,
        ]);

        QuoteItem::create([
            'quote_id' => $quote2->id,
            'item_type' => 'material',
            'description' => 'Raccords et joints',
            'quantity' => 1,
            'unit' => 'lot',
            'unit_price' => 20000,
            'total' => 20000,
        ]);

        QuoteItem::create([
            'quote_id' => $quote2->id,
            'item_type' => 'labor',
            'description' => 'Réparation fuite + remplacement',
            'quantity' => 1,
            'unit' => 'forfait',
            'unit_price' => 80000,
            'total' => 80000,
        ]);

        // Project 3: Meubles - 1 quote
        $quote3 = Quote::create([
            'project_id' => $projects[3]->id,
            'artisan_id' => $users['artisans'][3]->id, // Abdoulaye (Menuisier)
            'total_amount' => 550000,
            'material_amount' => 280000,
            'labor_amount' => 270000,
            'material_percentage' => 50.91,
            'labor_percentage' => 49.09,
            'valid_until' => now()->addDays(10),
            'status' => 'sent',
        ]);

        QuoteItem::create([
            'quote_id' => $quote3->id,
            'item_type' => 'material',
            'description' => 'Bois massif (Teck)',
            'quantity' => 5,
            'unit' => 'm²',
            'unit_price' => 35000,
            'total' => 175000,
        ]);

        QuoteItem::create([
            'quote_id' => $quote3->id,
            'item_type' => 'material',
            'description' => 'Quincaillerie et finitions',
            'quantity' => 1,
            'unit' => 'lot',
            'unit_price' => 105000,
            'total' => 105000,
        ]);

        QuoteItem::create([
            'quote_id' => $quote3->id,
            'item_type' => 'labor',
            'description' => 'Fabrication bibliothèque + bureau',
            'quantity' => 1,
            'unit' => 'forfait',
            'unit_price' => 270000,
            'total' => 270000,
        ]);
    }

    /**
     * Create completed project workflows with milestones, tokens, reviews
     */
    protected function createCompletedWorkflows($users): void
    {
        // Get trades
        $peintureTrade = Trade::where('name', 'like', '%Peintre%')->first() ?? Trade::first();
        $climatisationTrade = Trade::where('name', 'like', '%Climaticien%')->first() ?? Trade::first();

        // Completed Project 1: Peinture de maison
        $client = $users['clients'][0];
        $artisan = $users['artisans'][4]; // Moussa (Peintre)
        $vendor = $users['vendors'][0];

        $project = Project::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'trade_id' => $peintureTrade->id,
            'title' => 'Peinture complète maison',
            'description' => 'Peinture intérieure et extérieure d\'une villa (150m²)',
            'location' => new Point(5.3380, -4.0280),
            'address' => 'Cocody Riviera Golf, Abidjan',
            'status' => 'completed',
        ]);

        $quote = Quote::create([
            'project_id' => $project->id,
            'artisan_id' => $artisan->id,
            'total_amount' => 1200000,
            'material_amount' => 500000,
            'labor_amount' => 700000,
            'material_percentage' => 41.67,
            'labor_percentage' => 58.33,
            'valid_until' => now()->addDays(7),
            'status' => 'accepted',
        ]);

        $project->update([
            'accepted_quote_id' => $quote->id,
            'final_amount' => 1200000,
        ]);

        // Create escrow wallet
        $escrow = EscrowWallet::create([
            'project_id' => $project->id,
            'total_amount' => 1200000,
            'material_wallet' => 500000,
            'labor_wallet' => 700000,
            'material_spent' => 500000,
            'labor_released' => 700000,
            'status' => 'completed',
        ]);

        // Create material token (fully used)
        $token = MaterialToken::create([
            'code' => 'PA-' . strtoupper(substr(md5('test1'), 0, 6)),
            'project_id' => $project->id,
            'escrow_wallet_id' => $escrow->id,
            'vendor_id' => $vendor->id,
            'total_value' => 500000,
            'remaining_value' => 0,
            'expires_at' => now()->addDays(7),
            'status' => 'fully_used',
        ]);

        // Token redemption
        TokenRedemption::create([
            'token_id' => $token->id,
            'vendor_id' => $vendor->id,
            'artisan_id' => $artisan->id,
            'amount' => 500000,
            'vendor_location' => new Point(5.3365, -4.0268),
            'artisan_location' => new Point(5.3364, -4.0267),
            'distance_meters' => 15.5,
            'validation_method' => 'gps',
            'redeemed_at' => now()->subDays(10),
        ]);

        // Create milestones (all validated)
        $milestone1 = Milestone::create([
            'project_id' => $project->id,
            'title' => 'Préparation des surfaces',
            'description' => 'Ponçage et apprêt',
            'labor_percentage' => 30,
            'sequence_order' => 1,
            'status' => 'validated',
            'requires_photo' => true,
            'requires_otp' => true,
            'completed_at' => now()->subDays(8),
            'validated_at' => now()->subDays(8),
        ]);

        $laborPayment1 = LaborPayment::create([
            'milestone_id' => $milestone1->id,
            'artisan_id' => $artisan->id,
            'escrow_wallet_id' => $escrow->id,
            'amount' => 210000,
            'status' => 'completed',
            'scheduled_at' => now()->subDays(7),
            'paid_at' => now()->subDays(7),
        ]);

        $milestone2 = Milestone::create([
            'project_id' => $project->id,
            'title' => 'Première couche',
            'description' => 'Application première couche',
            'labor_percentage' => 40,
            'sequence_order' => 2,
            'status' => 'validated',
            'requires_photo' => true,
            'requires_otp' => true,
            'completed_at' => now()->subDays(5),
            'validated_at' => now()->subDays(5),
        ]);

        $laborPayment2 = LaborPayment::create([
            'milestone_id' => $milestone2->id,
            'artisan_id' => $artisan->id,
            'escrow_wallet_id' => $escrow->id,
            'amount' => 280000,
            'status' => 'completed',
            'scheduled_at' => now()->subDays(4),
            'paid_at' => now()->subDays(4),
        ]);

        $milestone3 = Milestone::create([
            'project_id' => $project->id,
            'title' => 'Finitions',
            'description' => 'Deuxième couche et retouches',
            'labor_percentage' => 30,
            'sequence_order' => 3,
            'status' => 'validated',
            'requires_photo' => true,
            'requires_otp' => true,
            'completed_at' => now()->subDays(2),
            'validated_at' => now()->subDays(2),
        ]);

        $laborPayment3 = LaborPayment::create([
            'milestone_id' => $milestone3->id,
            'artisan_id' => $artisan->id,
            'escrow_wallet_id' => $escrow->id,
            'amount' => 210000,
            'status' => 'completed',
            'scheduled_at' => now()->subDays(1),
            'paid_at' => now()->subDays(1),
        ]);

        // Create review
        Review::create([
            'project_id' => $project->id,
            'artisan_id' => $artisan->id,
            'client_id' => $client->id,
            'rating' => 5,
            'comment' => 'Excellent travail ! Très professionnel et respectueux des délais. Je recommande vivement.',
            'quality_rating' => 5,
            'communication_rating' => 5,
            'timeliness_rating' => 4,
            'professionalism_rating' => 5,
            'would_recommend' => true,
        ]);

        // Calculate score for this artisan
        $scoreController = new ScoreController();
        $scoreController->calculate($artisan->id);

        // Completed Project 2: Installation climatisation
        $client2 = $users['clients'][1];
        $artisan2 = $users['artisans'][8]; // Bakary (Climaticien)
        $vendor2 = $users['vendors'][1];

        $project2 = Project::create([
            'client_id' => $client2->id,
            'artisan_id' => $artisan2->id,
            'trade_id' => $climatisationTrade->id,
            'title' => 'Installation climatisation',
            'description' => 'Installation de 3 climatiseurs split',
            'location' => new Point(5.3500, -4.0100),
            'address' => 'Marcory Zone 4, Abidjan',
            'status' => 'completed',
        ]);

        $quote2 = Quote::create([
            'project_id' => $project2->id,
            'artisan_id' => $artisan2->id,
            'total_amount' => 1800000,
            'material_amount' => 1200000,
            'labor_amount' => 600000,
            'material_percentage' => 66.67,
            'labor_percentage' => 33.33,
            'valid_until' => now()->addDays(7),
            'status' => 'accepted',
        ]);

        $project2->update([
            'accepted_quote_id' => $quote2->id,
            'final_amount' => 1800000,
        ]);

        $escrow2 = EscrowWallet::create([
            'project_id' => $project2->id,
            'total_amount' => 1800000,
            'material_wallet' => 1200000,
            'labor_wallet' => 600000,
            'material_spent' => 1200000,
            'labor_released' => 600000,
            'status' => 'completed',
        ]);

        $token2 = MaterialToken::create([
            'code' => 'PA-' . strtoupper(substr(md5('test2'), 0, 6)),
            'project_id' => $project2->id,
            'escrow_wallet_id' => $escrow2->id,
            'vendor_id' => $vendor2->id,
            'total_value' => 1200000,
            'remaining_value' => 0,
            'expires_at' => now()->addDays(7),
            'status' => 'fully_used',
        ]);

        TokenRedemption::create([
            'token_id' => $token2->id,
            'vendor_id' => $vendor2->id,
            'artisan_id' => $artisan2->id,
            'amount' => 1200000,
            'vendor_location' => new Point(5.3501, -4.0101),
            'artisan_location' => new Point(5.3500, -4.0100),
            'distance_meters' => 22.8,
            'validation_method' => 'gps',
            'redeemed_at' => now()->subDays(6),
        ]);

        $milestone2_1 = Milestone::create([
            'project_id' => $project2->id,
            'title' => 'Installation complète',
            'description' => 'Installation des 3 climatiseurs',
            'labor_percentage' => 100,
            'sequence_order' => 1,
            'status' => 'validated',
            'requires_photo' => true,
            'requires_otp' => true,
            'completed_at' => now()->subDays(3),
            'validated_at' => now()->subDays(3),
        ]);

        LaborPayment::create([
            'milestone_id' => $milestone2_1->id,
            'artisan_id' => $artisan2->id,
            'escrow_wallet_id' => $escrow2->id,
            'amount' => 600000,
            'status' => 'completed',
            'scheduled_at' => now()->subDays(2),
            'paid_at' => now()->subDays(2),
        ]);

        Review::create([
            'project_id' => $project2->id,
            'artisan_id' => $artisan2->id,
            'client_id' => $client2->id,
            'rating' => 4,
            'comment' => 'Bon travail, installation professionnelle. Petit retard de 2 jours.',
            'quality_rating' => 5,
            'communication_rating' => 4,
            'timeliness_rating' => 3,
            'professionalism_rating' => 4,
            'would_recommend' => true,
        ]);

        $scoreController->calculate($artisan2->id);

        $this->command->info('✓ Created 2 completed projects with full workflows');
    }
}
