const fs = require('fs');
const path = require('path');

const districtsData = JSON.parse(fs.readFileSync(path.resolve(__dirname, '../extracted-districts.json'), 'utf8'));

// 22 Pôles urbains et villes stratégiques de Côte d'Ivoire
// Coordonnées calibrées sur la grille vectorielle 1000x1000
const citiesData = [
  {
    id: 'abidjan',
    name: 'Abidjan',
    type: 'metropole',
    population: '5 600 000 hab.',
    districtSlug: 'abidjan',
    x: 711.1,
    y: 798.7,
    description: 'Capitale économique, pôle majeur de demande et vivier d\'artisans qualifiés'
  },
  {
    id: 'yamoussoukro',
    name: 'Yamoussoukro',
    type: 'capitale',
    population: '360 000 hab.',
    districtSlug: 'yamoussoukro',
    x: 540.4,
    y: 590.8,
    description: 'Capitale politique et administrative de Côte d\'Ivoire'
  },
  {
    id: 'bouake',
    name: 'Bouaké',
    type: 'metropole',
    population: '830 000 hab.',
    districtSlug: 'gbeke',
    x: 608.3,
    y: 440.0,
    description: 'Carrefour commercial et logistique central de la Côte d\'Ivoire'
  },
  {
    id: 'korhogo',
    name: 'Korhogo',
    type: 'metropole',
    population: '440 000 hab.',
    districtSlug: 'poro',
    x: 462.4,
    y: 202.4,
    description: 'Grand pôle septentrional, artisanat traditionnel et agro-industrie'
  },
  {
    id: 'daloa',
    name: 'Daloa',
    type: 'metropole',
    population: '420 000 hab.',
    districtSlug: 'haut_sassandra',
    x: 390.0,
    y: 572.8,
    description: 'Cœur de la boucle du cacao et dynamique commerciale de l\'Ouest'
  },
  {
    id: 'san_pedro',
    name: 'San-Pédro',
    type: 'port',
    population: '390 000 hab.',
    districtSlug: 'san_pedro',
    x: 320.0,
    y: 890.0,
    description: 'Deuxième port de Côte d\'Ivoire, terminal d\'exportation et hub logistique littoral'
  },
  {
    id: 'man',
    name: 'Man',
    type: 'ville',
    population: '240 000 hab.',
    districtSlug: 'tonkpi',
    x: 180.0,
    y: 550.0,
    description: 'Cité des 18 montagnes, carrefour frontalier Ouest'
  },
  {
    id: 'gagnoa',
    name: 'Gagnoa',
    type: 'ville',
    population: '280 000 hab.',
    districtSlug: 'goh_djiboua',
    x: 460.0,
    y: 680.0,
    description: 'Bassin agro-industriel et artisanat du Centre-Ouest'
  },
  {
    id: 'divo',
    name: 'Divo',
    type: 'ville',
    population: '210 000 hab.',
    districtSlug: 'goh_djiboua',
    x: 520.0,
    y: 730.0,
    description: 'Pôle commercial clé du Gôh-Djiboua'
  },
  {
    id: 'abengourou',
    name: 'Abengourou',
    type: 'ville',
    population: '165 000 hab.',
    districtSlug: 'indenie_djuablin',
    x: 820.0,
    y: 580.0,
    description: 'Cité royale de l\'Indénié, liaison frontalière avec le Ghana'
  },
  {
    id: 'bondoukou',
    name: 'Bondoukou',
    type: 'ville',
    population: '140 000 hab.',
    districtSlug: 'gontougo',
    x: 850.0,
    y: 370.0,
    description: 'Ville aux mille mosquées, pôle d\'échanges Nord-Est'
  },
  {
    id: 'odienne',
    name: 'Odienné',
    type: 'ville',
    population: '110 000 hab.',
    districtSlug: 'denguele',
    x: 243.2,
    y: 203.0,
    description: 'Pôle stratégique du Nord-Ouest (Denguélé)'
  },
  {
    id: 'grand_bassam',
    name: 'Grand-Bassam',
    type: 'ville',
    population: '120 000 hab.',
    districtSlug: 'sud_comoe',
    x: 750.0,
    y: 830.0,
    description: 'Ville historique UNESCO, pôle touristique et technologique (VITIB)'
  },
  {
    id: 'agboville',
    name: 'Agboville',
    type: 'ville',
    population: '135 000 hab.',
    districtSlug: 'agneby_tiassa',
    x: 660.0,
    y: 720.0,
    description: 'Carrefour ferroviaire et agricole de l\'Agnéby-Tiassa'
  },
  {
    id: 'dabou',
    name: 'Dabou',
    type: 'ville',
    population: '115 000 hab.',
    districtSlug: 'grands_ponts',
    x: 650.0,
    y: 800.0,
    description: 'Pôle lagunaire et agro-industriel de la région des Grands Ponts'
  },
  {
    id: 'seguela',
    name: 'Séguéla',
    type: 'ville',
    population: '90 000 hab.',
    districtSlug: 'worodougou',
    x: 330.0,
    y: 390.0,
    description: 'Chef-lieu du Worodougou et centre minier'
  },
  {
    id: 'ferkessedougou',
    name: 'Ferkessédougou',
    type: 'ville',
    population: '125 000 hab.',
    districtSlug: 'poro',
    x: 520.0,
    y: 160.0,
    description: 'Carrefour logistique ferroviaire et routier vers le Mali et le Burkina Faso'
  },
  {
    id: 'dimbokro',
    name: 'Dimbokro',
    type: 'ville',
    population: '80 000 hab.',
    districtSlug: 'n_zi',
    x: 640.0,
    y: 600.0,
    description: 'Pôle historique du N\'Zi et boucle ferroviaire'
  },
  {
    id: 'toumodi',
    name: 'Toumodi',
    type: 'ville',
    population: '75 000 hab.',
    districtSlug: 'belier',
    x: 580.0,
    y: 620.0,
    description: 'Bélier / carrefour autoroutier Abidjan-Yamoussoukro'
  },
  {
    id: 'bouna',
    name: 'Bouna',
    type: 'ville',
    population: '60 000 hab.',
    districtSlug: 'bounkani',
    x: 890.0,
    y: 180.0,
    description: 'Porte d\'entrée du parc national de la Comoé et du Bounkani'
  },
  {
    id: 'sassandra',
    name: 'Sassandra',
    type: 'port',
    population: '70 000 hab.',
    districtSlug: 'gbokle',
    x: 370.0,
    y: 870.0,
    description: 'Ville côtière historique, pôle de pêche et du Gboklè'
  },
  {
    id: 'soubre',
    name: 'Soubré',
    type: 'ville',
    population: '175 000 hab.',
    districtSlug: 'nawa',
    x: 310.0,
    y: 720.0,
    description: 'Capitale de la région de la Nawa et grand barrage hydroélectrique'
  }
];

// 13 Communes du Grand Abidjan pour la loupe métropolitaine
const abidjanCommunesData = [
  { id: 'cocody', name: 'Cocody', zone: 'Nord-Est', density: 'Élevée', center: [340, 160], path: 'M300 130 C330 120 370 140 380 170 C375 200 330 210 310 180 Z' },
  { id: 'yopougon', name: 'Yopougon', zone: 'Ouest', density: 'Très élevée', center: [130, 200], path: 'M80 160 C130 140 180 170 175 230 C160 270 100 270 75 220 Z' },
  { id: 'abobo', name: 'Abobo', zone: 'Nord', density: 'Très élevée', center: [230, 90], path: 'M170 60 C230 40 280 70 280 120 C250 140 190 140 170 110 Z' },
  { id: 'plateau', name: 'Plateau', zone: 'Centre-Affaires', density: 'Moyenne (bureaux)', center: [240, 220], path: 'M220 200 C255 195 265 215 260 240 C240 250 220 245 220 220 Z' },
  { id: 'adjame', name: 'Adjamé', zone: 'Centre-Commerce', density: 'Très élevée', center: [225, 160], path: 'M200 140 C245 135 255 155 250 185 C225 195 205 185 200 160 Z' },
  { id: 'koumassi', name: 'Koumassi', zone: 'Sud-Est', density: 'Très élevée', center: [310, 310], path: 'M270 280 C320 275 350 295 345 335 C310 355 270 345 265 310 Z' },
  { id: 'marcory', name: 'Marcory', zone: 'Sud-Centre', density: 'Élevée', center: [250, 280], path: 'M220 260 C265 255 285 270 280 305 C255 320 225 315 220 285 Z' },
  { id: 'treichville', name: 'Treichville', zone: 'Sud-Port', density: 'Élevée', center: [200, 260], path: 'M175 240 C215 235 225 250 220 280 C195 295 175 290 170 265 Z' },
  { id: 'port_bouet', name: 'Port-Bouët', zone: 'Littoral-Aéroport', density: 'Moyenne', center: [330, 380], path: 'M250 350 C330 330 420 350 400 420 C320 440 240 410 245 370 Z' },
  { id: 'attecoube', name: 'Attécoubé', zone: 'Centre-Ouest', density: 'Élevée', center: [180, 180], path: 'M160 160 C195 150 210 175 200 205 C175 215 160 200 160 180 Z' },
  { id: 'bingerville', name: 'Bingerville', zone: 'Est-Lagunaire', density: 'En croissance', center: [430, 170], path: 'M385 140 C440 130 480 160 470 210 C430 230 380 210 380 170 Z' },
  { id: 'songon', name: 'Songon', zone: 'Périurbain-Ouest', density: 'Rurale/Mixte', center: [50, 240], path: 'M20 200 C70 190 85 240 75 280 C35 290 15 250 20 210 Z' },
  { id: 'anyama', name: 'Anyama', zone: 'Périurbain-Nord', density: 'En forte croissance', center: [230, 25], path: 'M180 5 C240 5 280 25 270 55 C220 70 180 55 180 25 Z' }
];

const tsContent = `// Données vectorielles et cartographiques de la Côte d'Ivoire
// Calibré sur la norme standard 1000x1000 (viewBox 0 0 1000 1000)
// Extrait fidèlement des sources topographiques EditMapStudio / MapSVG

export interface DistrictGeoData {
  isoCode: string;
  slug: string;
  name: string;
  shortName: string;
  chefLieu: string;
  center: [number, number];
  regions: string[];
  path: string;
}

export type CityType = 'metropole' | 'capitale' | 'port' | 'ville';

export interface CityGeoData {
  id: string;
  name: string;
  type: CityType;
  population: string;
  districtSlug: string;
  x: number;
  y: number;
  description: string;
}

export interface AbidjanCommuneGeoData {
  id: string;
  name: string;
  zone: string;
  density: string;
  center: [number, number];
  path: string;
}

export const IVORY_COAST_DISTRICTS: DistrictGeoData[] = ${JSON.stringify(districtsData, null, 2)};

export const IVORY_COAST_CITIES: CityGeoData[] = ${JSON.stringify(citiesData, null, 2)};

export const ABIDJAN_COMMUNES_GEODATA: AbidjanCommuneGeoData[] = ${JSON.stringify(abidjanCommunesData, null, 2)};

/**
 * Retourne la couleur dynamique choroplèthe en fonction de la valeur normalisée (0 à 100)
 */
export function getChoroplethColor(value: number, metric: 'actors' | 'volume' | 'rate'): string {
  const clamp = Math.max(0, Math.min(100, value));
  
  if (metric === 'rate') {
    // Vert / Succès / Taux de réalisation
    if (clamp >= 85) return '#10b981'; // emerald-500
    if (clamp >= 70) return '#34d399'; // emerald-400
    if (clamp >= 50) return '#f59e0b'; // amber-500
    if (clamp >= 30) return '#fb923c'; // orange-400
    return '#ef4444'; // red-500
  }
  
  if (metric === 'volume') {
    // Teintes Dorées / Ambre / FCFA
    if (clamp >= 75) return '#d97706'; // amber-600
    if (clamp >= 50) return '#f59e0b'; // amber-500
    if (clamp >= 25) return '#fbbf24'; // amber-400
    if (clamp >= 10) return '#fde68a'; // amber-200
    return '#fef3c7'; // amber-100
  }

  // Teintes Bleues / Indigo / Densité d'acteurs
  if (clamp >= 75) return '#3b82f6'; // blue-500
  if (clamp >= 50) return '#60a5fa'; // blue-400
  if (clamp >= 25) return '#93c5fd'; // blue-300
  if (clamp >= 10) return '#bfdbfe'; // blue-200
  return '#e2e8f0'; // slate-200
}
`;

const outputPath = path.resolve(__dirname, '../backend-proartisan/resources/js/pages/admin/panels/ivoryCoastGeoData.ts');
fs.writeFileSync(outputPath, tsContent, 'utf8');
console.log('Successfully generated:', outputPath, 'with', districtsData.length, 'districts,', citiesData.length, 'cities, and', abidjanCommunesData.length, 'communes.');
