<?php

namespace Database\Seeders;

use App\Models\Sector;
use App\Models\Trade;
use Illuminate\Database\Seeder;

class SectorSeeder extends Seeder
{
    public function run(): void
    {
        $sectors = [
            [
                'name'  => 'Électricité',
                'icon'  => 'zap',
                'color' => '#F39C12',
                'trades' => [
                    'Électricien bâtiment', 'Électricien industriel', 'Installateur solaire photovoltaïque',
                    'Technicien en climatisation électrique', 'Câbleur réseau', 'Technicien alarme & sécurité',
                    'Électricien automobile', 'Installateur domotique',
                ],
            ],
            [
                'name'  => 'Plomberie',
                'icon'  => 'droplets',
                'color' => '#3498DB',
                'trades' => [
                    'Plombier sanitaire', 'Plombier industriel', 'Installateur climatisation & froid',
                    'Technicien forage & pompage', 'Technicien traitement de l\'eau',
                    'Installateur gaz', 'Technicien chauffe-eau solaire',
                ],
            ],
            [
                'name'  => 'Maçonnerie',
                'icon'  => 'layers',
                'color' => '#95A5A6',
                'trades' => [
                    'Maçon gros œuvre', 'Coffreur-bancheur', 'Ferrailleur-béton armé',
                    'Maçon pisé / banco', 'Finisseur béton', 'Carreleur-mosaïste',
                    'Monteur préfabriqué', 'Tailleur de pierre',
                ],
            ],
            [
                'name'  => 'Menuiserie',
                'icon'  => 'scissors',
                'color' => '#E67E22',
                'trades' => [
                    'Menuisier bois', 'Menuisier aluminium', 'Menuisier PVC',
                    'Ébéniste', 'Charpentier bois', 'Poseur de parquet',
                    'Serrurier-métalliste', 'Forgeron-ferronnerie',
                ],
            ],
            [
                'name'  => 'Peinture & Revêtements',
                'icon'  => 'paintbrush',
                'color' => '#9B59B6',
                'trades' => [
                    'Peintre en bâtiment', 'Peintre décorateur', 'Plâtrier',
                    'Poseur de papier peint', 'Applicateur enduits & crépis',
                    'Peintre industriel / anti-corrosion', 'Poseur de faux plafond',
                ],
            ],
            [
                'name'  => 'Climatisation & Froid',
                'icon'  => 'wind',
                'color' => '#1ABC9C',
                'trades' => [
                    'Technicien climatiseur split', 'Technicien chambre froide',
                    'Réparateur réfrigérateur / congélateur', 'Installateur ventilation',
                    'Technicien pompe à chaleur',
                ],
            ],
            [
                'name'  => 'Couverture & Toiture',
                'icon'  => 'home',
                'color' => '#E74C3C',
                'trades' => [
                    'Couvreur tôle ondulée', 'Couvreur tuile', 'Couvreur ardoise',
                    'Étanchéiste toiture terrasse', 'Charpentier-couvreur',
                    'Zingueur', 'Poseur de vélux',
                ],
            ],
            [
                'name'  => 'Carrelage & Sol',
                'icon'  => 'grid',
                'color' => '#F39C12',
                'trades' => [
                    'Carreleur sol & mur', 'Poseur de faïence', 'Mosaïste',
                    'Poseur de marbre', 'Poseur de parquet stratifié',
                    'Poseur de moquette & linoléum',
                ],
            ],
            [
                'name'  => 'Soudure & Métallerie',
                'icon'  => 'flame',
                'color' => '#7F8C8D',
                'trades' => [
                    'Soudeur MIG-MAG', 'Soudeur TIG', 'Soudeur arc électrique',
                    'Chaudronnier', 'Tôlier carrossier', 'Forgeron',
                    'Monteur structures métalliques', 'Serrurier industriel',
                ],
            ],
            [
                'name'  => 'Jardinage & Espaces verts',
                'icon'  => 'trees',
                'color' => '#27AE60',
                'trades' => [
                    'Jardinier paysagiste', 'Élageur', 'Poseur de gazon',
                    'Technicien irrigation', 'Tailleur de haies',
                    'Spécialiste plantes tropicales',
                ],
            ],
            [
                'name'  => 'Nettoyage & Entretien',
                'icon'  => 'sparkles',
                'color' => '#3498DB',
                'trades' => [
                    'Agent de nettoyage bâtiment', 'Nettoyeur façades',
                    'Nettoyeur haute pression', 'Technicien traitement nuisibles',
                    'Agent désinfection', 'Nettoyeur de vitres',
                ],
            ],
            [
                'name'  => 'Informatique & Électronique',
                'icon'  => 'monitor',
                'color' => '#2C3E50',
                'trades' => [
                    'Technicien réseau & câblage', 'Réparateur PC & Mac',
                    'Réparateur smartphone & tablette', 'Installateur vidéosurveillance',
                    'Technicien son & image', 'Technicien imprimante & copier',
                    'Installateur antenne & parabole',
                ],
            ],
        ];

        foreach ($sectors as $sectorData) {
            $trades = $sectorData['trades'];
            unset($sectorData['trades']);

            $sector = Sector::create($sectorData);

            foreach ($trades as $tradeName) {
                Trade::create([
                    'sector_id' => $sector->id,
                    'name'      => $tradeName,
                ]);
            }
        }
    }
}
