// Types d'acteurs et de missions de « Cartographie & Territoires » : libellés,
// couleurs et champs de la matrice par zone, communs à la carte et au tableau.

import type { TerritoryTypeKey, TerritoryZoneRow } from '../shared/types';

export interface TerritoryTypeDef {
    key: TerritoryTypeKey;
    label: string;
    singular: string;
    /** Champ de la matrice portant l'effectif du type. */
    field: 'clients' | 'artisans' | 'livreurs' | 'fournisseurs' | 'missions_total';
    color: string;
}

export const TERRITORY_TYPES: TerritoryTypeDef[] = [
    { key: 'client', label: 'Clients', singular: 'Client', field: 'clients', color: '#2563eb' },
    { key: 'artisan', label: 'Artisans', singular: 'Artisan', field: 'artisans', color: '#d97706' },
    { key: 'livreur', label: 'Livreurs', singular: 'Livreur', field: 'livreurs', color: '#475569' },
    { key: 'fournisseur', label: 'Quincailleries', singular: 'Quincaillerie', field: 'fournisseurs', color: '#059669' },
    { key: 'mission', label: 'Missions', singular: 'Mission', field: 'missions_total', color: '#7c3aed' },
];

export const UNLOCATED_SLUG = 'non_renseigne';

/** Aucun type coché vaut « tout » : la sélection vide n'affiche jamais une carte vide. */
export function effectiveTypes(active: TerritoryTypeKey[]): TerritoryTypeDef[] {
    return active.length === 0 ? TERRITORY_TYPES : TERRITORY_TYPES.filter((type) => active.includes(type.key));
}

/** Effectif d'une zone pour les types cochés. */
export function countForTypes(row: TerritoryZoneRow | undefined, active: TerritoryTypeKey[]): number {
    if (!row) return 0;
    return effectiveTypes(active).reduce((sum, type) => sum + (row[type.field] ?? 0), 0);
}

export function emptyZoneRow(slug: string, name: string, type: TerritoryZoneRow['type']): TerritoryZoneRow {
    return {
        slug,
        name,
        type,
        clients: 0,
        clients_mission_active: 0,
        artisans: 0,
        artisans_kyc_actif: 0,
        livreurs: 0,
        livreurs_en_course: 0,
        fournisseurs: 0,
        fournisseurs_agrees: 0,
        missions_total: 0,
        missions_en_cours: 0,
        missions_terminees: 0,
        litiges: 0,
        volume_fcfa: 0,
        total_actors: 0,
        realization_rate: 0,
        dispute_rate: 0,
    };
}

export const EMPTY_MATRIX = {
    national: emptyZoneRow('all', "Côte d'Ivoire", 'national'),
    unlocated: emptyZoneRow(UNLOCATED_SLUG, 'Commune non renseignée', 'unlocated'),
    districts: {},
    communes: {},
};
