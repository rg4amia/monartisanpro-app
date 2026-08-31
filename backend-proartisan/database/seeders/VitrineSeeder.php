<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vitrine\VitrineSlide;
use App\Models\Vitrine\VitrineArtisanDuMois;
use App\Models\Vitrine\VitrineArticle;
use App\Models\Vitrine\VitrineVideo;
use App\Models\Vitrine\VitrineFormation;
use App\Models\Vitrine\VitrineRecrutement;
use App\Models\Vitrine\VitrinePopup;
use App\Models\Vitrine\VitrineSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VitrineSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Slides
        VitrineSlide::updateOrCreate(
            ['titre' => 'Des artisans qualifiés pour tous vos travaux en Côte d\'Ivoire'],
            [
                'sous_titre' => 'Électricité, plomberie, maçonnerie, menuiserie... Connectez-vous avec des pros de confiance agréés par notre label de qualité unique.',
                'image_url' => 'https://images.unsplash.com/photo-1581092921461-eab62e97a780?auto=format&fit=crop&w=1200&q=80',
                'cta_texte' => 'Trouver un artisan',
                'cta_lien' => '/services',
                'ordre' => 1,
                'actif' => true,
            ]
        );

        VitrineSlide::updateOrCreate(
            ['titre' => 'Équipez vos chantiers en toute sécurité avec notre séquestre intelligent'],
            [
                'sous_titre' => 'Acomptes bloqués et libérés jalon par jalon. Paiements garantis Wave & Orange Money pour artisans et fournisseurs.',
                'image_url' => 'https://images.unsplash.com/photo-1541888946425-d81bb19240f5?auto=format&fit=crop&w=1200&q=80',
                'cta_texte' => 'Découvrir nos services',
                'cta_lien' => '/services',
                'ordre' => 2,
                'actif' => true,
            ]
        );

        // 2. Seed Artisan du Mois
        $artisan = User::where('role', 'artisan')->first();
        if ($artisan) {
            VitrineArtisanDuMois::updateOrCreate(
                ['mois' => now()->startOfMonth()->toDateString()],
                [
                    'user_id' => $artisan->id,
                    'photo_override_url' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=300&q=80',
                    'texte_editorial' => 'Kouamé Bah s\'est distingué ce mois-ci par sa réactivité exceptionnelle sur les chantiers de Yopougon et Cocody, ainsi que par un score ProsArtisan parfait de 980/1000 basé sur 14 avis clients.',
                    'actif' => true,
                ]
            );
        }

        // 3. Seed Articles / Actualités
        VitrineArticle::updateOrCreate(
            ['slug' => 'lancement-de-prosartisan-plateforme-de-confiance'],
            [
                'titre' => 'Lancement officiel de ProsArtisan en Côte d\'Ivoire',
                'contenu' => '<p>Nous sommes fiers de vous annoncer le lancement officiel de <strong>ProsArtisan</strong>, la première marketplace ivoirienne sécurisée par séquestre bancaire pour la mise en relation avec des artisans qualifiés.</p><p>Notre objectif est de professionnaliser le secteur informel du bâtiment en Côte d\'Ivoire, en offrant des outils digitaux innovants (J-Codes, QR codes quincaillerie, validation GPS anti-fraude) et des solutions de micro-crédit d\'urgence pour les artisans partenaires.</p>',
                'image_url' => 'https://images.unsplash.com/photo-1531403009284-440f080d1e12?auto=format&fit=crop&w=800&q=80',
                'categorie' => 'actualite',
                'publie' => true,
                'publie_at' => now(),
            ]
        );

        VitrineArticle::updateOrCreate(
            ['slug' => 'partenariat-quincailleries-agreees-abidjan'],
            [
                'titre' => 'Plus de 50 quincailleries agréées rejoignent le réseau ProsArtisan',
                'contenu' => '<p>Afin de fluidifier l\'approvisionnement en matériaux sur vos chantiers, nous avons noué des partenariats avec plus de 50 quincailleries à Yopougon, Cocody, Koumassi et Marcory.</p><p>Désormais, les artisans ProsArtisan peuvent générer des J-Codes pour récupérer instantanément le ciment, le fer ou la peinture nécessaires, réglés via le portefeuille matériaux sécurisé.</p>',
                'image_url' => 'https://images.unsplash.com/photo-1513828729020-56f2d0d7e7a7?auto=format&fit=crop&w=800&q=80',
                'categorie' => 'partenariat',
                'publie' => true,
                'publie_at' => now()->subDays(5),
            ]
        );

        // 4. Seed Videos
        VitrineVideo::updateOrCreate(
            ['titre' => 'Comment commander un artisan sur ProsArtisan ?'],
            [
                'description' => 'Découvrez le parcours client en vidéo : soumission du besoin, diagnostic Gemini IA, validation du devis par séquestre Wave et livraison des jalons.',
                'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ', // Mock video link
                'thumbnail_url' => 'https://images.unsplash.com/photo-1541534741688-6078c6bfb5c5?auto=format&fit=crop&w=600&q=80',
                'categorie' => 'capsule',
                'ordre' => 1,
                'actif' => true,
            ]
        );

        VitrineVideo::updateOrCreate(
            ['titre' => 'Témoignage de Seydou, maçon à Adjamé'],
            [
                'description' => 'Seydou nous raconte comment l\'accès au micro-crédit d\'urgence et aux J-Codes matériaux a transformé son activité quotidienne et fidélisé ses clients.',
                'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1504307651254-35680f356dfd?auto=format&fit=crop&w=600&q=80',
                'categorie' => 'temoignage',
                'ordre' => 2,
                'actif' => true,
            ]
        );

        // 5. Seed Formations
        VitrineFormation::updateOrCreate(
            ['titre' => 'Normes électriques de sécurité NF C 15-100 en Côte d\'Ivoire'],
            [
                'description' => 'Une session intensive de 2 jours destinée aux électriciens du bâtiment souhaitant labelliser leurs compétences et rejoindre le réseau d\'artisans prioritaires ProsArtisan.',
                'image_url' => 'https://images.unsplash.com/photo-1621905251189-08b45d6a269e?auto=format&fit=crop&w=800&q=80',
                'date_debut' => now()->addDays(15)->toDateString(),
                'date_fin' => now()->addDays(16)->toDateString(),
                'lieu' => 'Maison des Artisans, Treichville, Abidjan',
                'formateur' => 'M. Charles Koffi, Ingénieur électricien',
                'places_total' => 20,
                'places_restantes' => 12,
                'tarif' => 25000,
                'lien_inscription' => 'https://prosartisan.ci/inscription-formation-1',
                'actif' => true,
            ]
        );

        VitrineFormation::updateOrCreate(
            ['titre' => 'Gestion de budget et création de devis ProsArtisan'],
            [
                'description' => 'Apprenez à utiliser l\'application mobile ProsArtisan pour estimer vos matériaux, créer des devis équilibrés et optimiser vos jalons financiers.',
                'image_url' => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=800&q=80',
                'date_debut' => now()->addDays(30)->toDateString(),
                'date_fin' => now()->addDays(30)->toDateString(),
                'lieu' => 'Siège ProsArtisan, Plateau, Abidjan',
                'formateur' => 'Cabinet d\'accompagnement PME CI',
                'places_total' => 30,
                'places_restantes' => 30,
                'tarif' => 0, // Gratuite
                'lien_inscription' => 'https://prosartisan.ci/inscription-formation-2',
                'actif' => true,
            ]
        );

        // 6. Seed Recrutements
        VitrineRecrutement::updateOrCreate(
            ['titre' => 'Recrutement de 15 Plombiers qualifiés pour chantiers résidentiels'],
            [
                'description' => 'Dans le cadre de projets immobiliers à Cocody Angré, nous recherchons des plombiers qualifiés ayant au moins 3 ans d\'expérience. Inscription et KYC ProsArtisan obligatoires.',
                'metier' => 'Plombier',
                'lieu' => 'Abidjan - Cocody',
                'type_contrat' => 'freelance',
                'date_limite' => now()->addDays(20)->toDateString(),
                'contact_email' => 'recrutement@prosartisan.ci',
                'actif' => true,
            ]
        );

        // 7. Seed Pop-ups
        VitrinePopup::updateOrCreate(
            ['titre' => 'Bénéficiez de 5% de remise sur votre premier diagnostic de chantier !'],
            [
                'contenu' => 'Utilisez le code promo PROMO5 lors de l\'estimation IA de vos travaux. Valable pour toute première commande validée avant la fin du mois.',
                'image_url' => 'https://images.unsplash.com/photo-1504307651254-35680f356dfd?auto=format&fit=crop&w=400&q=80',
                'lien_cta' => '/promo-first',
                'texte_cta' => 'Activer la remise',
                'date_debut' => now()->subDays(1),
                'date_fin' => now()->addDays(10),
                'actif' => true,
            ]
        );

        // 8. Seed Settings
        VitrineSetting::updateOrCreate(
            ['cle' => 'vitrine_hero_title'],
            ['valeur' => 'La confiance au cœur de l\'artisanat ivoirien']
        );
        VitrineSetting::updateOrCreate(
            ['cle' => 'vitrine_hero_subtitle'],
            ['valeur' => 'Mise en relation sécurisée, artisans qualifiés et livraison de chantiers simplifiée.']
        );
        VitrineSetting::updateOrCreate(
            ['cle' => 'contact_phone_vitrine'],
            ['valeur' => '+225 01 60 60 61 83']
        );
        VitrineSetting::updateOrCreate(
            ['cle' => 'contact_email_vitrine'],
            ['valeur' => 'info@prosartisan.net']
        );

        // 9. Indicateurs de statistiques Hero Vitrine
        VitrineSetting::updateOrCreate(
            ['cle' => 'stat_artisans_valeur'],
            ['valeur' => '2 500+']
        );
        VitrineSetting::updateOrCreate(
            ['cle' => 'stat_artisans_label'],
            ['valeur' => 'Artisans agréés']
        );
        VitrineSetting::updateOrCreate(
            ['cle' => 'stat_missions_valeur'],
            ['valeur' => '14 800+']
        );
        VitrineSetting::updateOrCreate(
            ['cle' => 'stat_missions_label'],
            ['valeur' => 'Missions terminées']
        );
        VitrineSetting::updateOrCreate(
            ['cle' => 'stat_communes_valeur'],
            ['valeur' => '10 Abidjan']
        );
        VitrineSetting::updateOrCreate(
            ['cle' => 'stat_communes_label'],
            ['valeur' => 'Communes desservies']
        );
        VitrineSetting::updateOrCreate(
            ['cle' => 'stat_satisfaction_valeur'],
            ['valeur' => '4.8 / 5']
        );
        VitrineSetting::updateOrCreate(
            ['cle' => 'stat_satisfaction_label'],
            ['valeur' => 'Satisfaction client']
        );
    }
}
