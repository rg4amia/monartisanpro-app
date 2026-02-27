export type UserRole = 'client' | 'artisan' | 'fournisseur';

export interface Category {
  id: string;
  name: string;
  icon: string;
  color: string;
}

export interface Artisan {
  id: string;
  name: string;
  trade: string;
  photo: string;
  scoreNzassa: number;
  rating: number;
  completedMissions: number;
  location: string;
  distance: string;
  reviews: Review[];
}

export interface Review {
  id: string;
  clientName: string;
  rating: number;
  comment: string;
  date: string;
}

export interface Mission {
  id: string;
  title: string;
  description: string;
  category: string;
  status: 'devis' | 'financee' | 'en_cours' | 'jalons' | 'terminee';
  clientName: string;
  artisanName: string;
  totalAmount: number;
  laborAmount: number;
  materialsAmount: number;
  location: string;
  createdAt: string;
  milestones: Milestone[];
  urgency: 'faible' | 'moyen' | 'urgent';
}

export interface Milestone {
  id: string;
  title: string;
  amount: number;
  targetDate: string;
  status: 'pending' | 'in_progress' | 'completed' | 'validated';
  photoProof?: string;
}

export interface QuoteLine {
  id: string;
  type: 'labor' | 'material';
  description: string;
  quantity: number;
  unitPrice: number;
}

export interface Transaction {
  id: string;
  jCode: string;
  artisanName: string;
  amount: number;
  date: string;
  status: 'validee' | 'en_attente' | 'payee';
  gpsValid: boolean;
}

export interface NotificationItem {
  id: string;
  type: 'payment' | 'validation' | 'alert' | 'litige';
  title: string;
  message: string;
  date: string;
  read: boolean;
}

export const categories: Category[] = [
  { id: '1', name: 'Plomberie', icon: 'Droplets', color: '#3498DB' },
  { id: '2', name: 'Électricité', icon: 'Zap', color: '#F39C12' },
  { id: '3', name: 'Maçonnerie', icon: 'Brick', color: '#E67E22' },
  { id: '4', name: 'Menuiserie', icon: 'Hammer', color: '#8B4513' },
  { id: '5', name: 'Peinture', icon: 'Paintbrush', color: '#9B59B6' },
  { id: '6', name: 'Carrelage', icon: 'LayoutGrid', color: '#1ABC9C' },
];

export const artisans: Artisan[] = [
  {
    id: '1',
    name: 'Kouamé Yao Jean',
    trade: 'Plombier',
    photo: 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=200&h=200&fit=crop',
    scoreNzassa: 85,
    rating: 4.7,
    completedMissions: 143,
    location: 'Cocody, Abidjan',
    distance: '2.3 km',
    reviews: [
      { id: '1', clientName: 'Awa Traoré', rating: 5, comment: 'Excellent travail, très professionnel et ponctuel.', date: '2025-12-15' },
      { id: '2', clientName: 'Mamadou Koné', rating: 4, comment: 'Bon travail, je recommande.', date: '2025-11-28' },
      { id: '3', clientName: 'Fatou Diallo', rating: 5, comment: 'Rapide et efficace. Le meilleur plombier de Cocody!', date: '2025-11-10' },
    ],
  },
  {
    id: '2',
    name: 'Bamba Issouf',
    trade: 'Électricien',
    photo: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200&h=200&fit=crop',
    scoreNzassa: 72,
    rating: 4.4,
    completedMissions: 98,
    location: 'Yopougon, Abidjan',
    distance: '5.1 km',
    reviews: [
      { id: '1', clientName: 'Aminata Coulibaly', rating: 4, comment: 'Travail correct, bon prix.', date: '2025-12-01' },
      { id: '2', clientName: 'Koffi Aka', rating: 5, comment: 'Très compétent et honnête.', date: '2025-11-20' },
    ],
  },
  {
    id: '3',
    name: 'Touré Abdoulaye',
    trade: 'Maçon',
    photo: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=200&h=200&fit=crop',
    scoreNzassa: 35,
    rating: 3.2,
    completedMissions: 27,
    location: 'Adjamé, Abidjan',
    distance: '3.8 km',
    reviews: [
      { id: '1', clientName: 'Marie Konan', rating: 3, comment: 'Travail acceptable mais délais non respectés.', date: '2025-10-15' },
    ],
  },
  {
    id: '4',
    name: 'Diallo Moussa',
    trade: 'Menuisier',
    photo: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=200&h=200&fit=crop',
    scoreNzassa: 91,
    rating: 4.9,
    completedMissions: 215,
    location: 'Plateau, Abidjan',
    distance: '1.5 km',
    reviews: [
      { id: '1', clientName: 'Adjoua Brou', rating: 5, comment: 'Artisan exceptionnel! Mobilier sur mesure parfait.', date: '2025-12-20' },
      { id: '2', clientName: 'Ibrahim Sanogo', rating: 5, comment: "Le meilleur menuisier d'Abidjan, sans hésitation.", date: '2025-12-05' },
    ],
  },
];

export const missions: Mission[] = [
  {
    id: '1',
    title: "Réparation fuite d'eau cuisine",
    description: "Fuite importante sous l'évier de la cuisine. Besoin de remplacement du siphon et vérification des joints.",
    category: 'Plomberie',
    status: 'en_cours',
    clientName: 'Awa Traoré',
    artisanName: 'Kouamé Yao Jean',
    totalAmount: 185000,
    laborAmount: 75000,
    materialsAmount: 110000,
    location: 'Cocody Angré, Abidjan',
    createdAt: '2026-02-20',
    urgency: 'urgent',
    milestones: [
      { id: '1', title: 'Diagnostic et devis', amount: 15000, targetDate: '2026-02-21', status: 'completed' },
      { id: '2', title: 'Achat matériaux', amount: 110000, targetDate: '2026-02-22', status: 'completed' },
      { id: '3', title: 'Réparation', amount: 45000, targetDate: '2026-02-24', status: 'in_progress' },
      { id: '4', title: 'Vérification finale', amount: 15000, targetDate: '2026-02-25', status: 'pending' },
    ],
  },
  {
    id: '2',
    title: 'Installation tableau électrique',
    description: "Installation complète d'un nouveau tableau électrique pour villa de 4 pièces.",
    category: 'Électricité',
    status: 'devis',
    clientName: 'Mamadou Koné',
    artisanName: 'Bamba Issouf',
    totalAmount: 2500000,
    laborAmount: 800000,
    materialsAmount: 1700000,
    location: 'Yopougon Maroc, Abidjan',
    createdAt: '2026-02-25',
    urgency: 'moyen',
    milestones: [
      { id: '1', title: 'Étude technique', amount: 150000, targetDate: '2026-03-01', status: 'pending' },
      { id: '2', title: 'Achat matériaux', amount: 1700000, targetDate: '2026-03-05', status: 'pending' },
      { id: '3', title: 'Installation', amount: 500000, targetDate: '2026-03-10', status: 'pending' },
      { id: '4', title: 'Tests et mise en service', amount: 150000, targetDate: '2026-03-12', status: 'pending' },
    ],
  },
  {
    id: '3',
    title: 'Peinture salon et 2 chambres',
    description: 'Peinture intérieure complète du salon et de deux chambres. Préparation des murs incluse.',
    category: 'Peinture',
    status: 'terminee',
    clientName: 'Fatou Diallo',
    artisanName: 'Kouamé Yao Jean',
    totalAmount: 350000,
    laborAmount: 200000,
    materialsAmount: 150000,
    location: 'Cocody Riviera, Abidjan',
    createdAt: '2026-01-10',
    urgency: 'faible',
    milestones: [
      { id: '1', title: 'Préparation des murs', amount: 50000, targetDate: '2026-01-15', status: 'validated' },
      { id: '2', title: 'Première couche', amount: 100000, targetDate: '2026-01-18', status: 'validated' },
      { id: '3', title: 'Finitions', amount: 100000, targetDate: '2026-01-20', status: 'validated' },
      { id: '4', title: 'Nettoyage final', amount: 100000, targetDate: '2026-01-22', status: 'validated' },
    ],
  },
];

export const transactions: Transaction[] = [
  { id: '1', jCode: 'PA-4827', artisanName: 'Kouamé Yao Jean', amount: 110000, date: '2026-02-22', status: 'payee', gpsValid: true },
  { id: '2', jCode: 'PA-5193', artisanName: 'Bamba Issouf', amount: 85000, date: '2026-02-25', status: 'validee', gpsValid: true },
  { id: '3', jCode: 'PA-6012', artisanName: 'Touré Abdoulaye', amount: 250000, date: '2026-02-26', status: 'en_attente', gpsValid: false },
];

export const notificationsList: NotificationItem[] = [
  { id: '1', type: 'payment', title: 'Paiement reçu', message: "Vous avez reçu 75 000 FCFA pour la mission \"Réparation fuite d'eau\"", date: '2026-02-27', read: false },
  { id: '2', type: 'validation', title: 'Jalon validé', message: 'Le jalon "Achat matériaux" a été validé par le client', date: '2026-02-26', read: false },
  { id: '3', type: 'alert', title: 'Nouvelle mission', message: 'Un client à Cocody recherche un plombier', date: '2026-02-25', read: true },
  { id: '4', type: 'litige', title: 'Litige résolu', message: 'Le litige #LIT-0042 a été résolu en votre faveur', date: '2026-02-24', read: true },
  { id: '5', type: 'payment', title: 'J-Code validé', message: 'Le J-Code PA-4827 a été validé chez QuinCiv Cocody', date: '2026-02-22', read: true },
];

export const scoreBreakdown = {
  fiabilite: 88,
  integrite: 82,
  qualite: 90,
  reactivite: 78,
};

export function formatFCFA(amount: number): string {
  return amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' FCFA';
}

export function getScoreColor(score: number): string {
  if (score < 40) return '#E74C3C';
  if (score <= 70) return '#E67E22';
  return '#27AE60';
}
