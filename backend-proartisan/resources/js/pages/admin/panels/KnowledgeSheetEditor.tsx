// Formulaire d'une fiche technique de la base de connaissances (Chantier 23) :
// relecture d'une fiche rédigée par l'IA ou saisie manuelle.

import type { ReactNode } from 'react';

import { actionButtonClass } from '../shared';

export interface KnowledgeDosage {
    element: string;
    ratio: string;
    unite_mesure_locale: string;
}

export interface KnowledgeMaterial {
    nom: string;
    substitut_acceptable: string;
    disponibilite: string;
}

export interface KnowledgeSheet {
    id?: string;
    norme_origine: { source: string; reference_article: string; titre_original: string; texte_brut: string };
    alternative_prosartisan: {
        titre_vulgarise: string;
        methode_execution: string;
        bouclier_autorite: string;
        dosages_recommandes: KnowledgeDosage[];
        materiaux_recommandes: KnowledgeMaterial[];
    };
    cout_estime_local: { gamme_prix: string; estimation_m2_fcfa: string; justification_economique: string };
    metadata: { tags_pathologies: string[]; type_ouvrage: string };
}

export function emptySheet(): KnowledgeSheet {
    return {
        norme_origine: { source: '', reference_article: '', titre_original: '', texte_brut: '' },
        alternative_prosartisan: {
            titre_vulgarise: '',
            methode_execution: '',
            bouclier_autorite: '',
            dosages_recommandes: [],
            materiaux_recommandes: [],
        },
        cout_estime_local: { gamme_prix: '', estimation_m2_fcfa: '', justification_economique: '' },
        metadata: { tags_pathologies: [], type_ouvrage: '' },
    };
}

const fieldClass =
    'w-full rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-2 text-sm text-[var(--admin-text)]';

function Field({ label, htmlFor, children, hint }: { label: string; htmlFor: string; children: ReactNode; hint?: string }) {
    return (
        <div>
            <label htmlFor={htmlFor} className="mb-1 block text-xs font-semibold text-[var(--admin-text)]">
                {label}
            </label>
            {children}
            {hint ? <p className="mt-1 text-[11px] text-[var(--admin-muted)]">{hint}</p> : null}
        </div>
    );
}

export function KnowledgeSheetEditor({
    sheet,
    onChange,
    readOnly = false,
}: {
    sheet: KnowledgeSheet;
    onChange: (sheet: KnowledgeSheet) => void;
    readOnly?: boolean;
}) {
    const alt = sheet.alternative_prosartisan;

    const setAlt = (patch: Partial<KnowledgeSheet['alternative_prosartisan']>) =>
        onChange({ ...sheet, alternative_prosartisan: { ...alt, ...patch } });
    const setNorme = (patch: Partial<KnowledgeSheet['norme_origine']>) =>
        onChange({ ...sheet, norme_origine: { ...sheet.norme_origine, ...patch } });
    const setCout = (patch: Partial<KnowledgeSheet['cout_estime_local']>) =>
        onChange({ ...sheet, cout_estime_local: { ...sheet.cout_estime_local, ...patch } });
    const setMeta = (patch: Partial<KnowledgeSheet['metadata']>) => onChange({ ...sheet, metadata: { ...sheet.metadata, ...patch } });

    const setDosage = (index: number, patch: Partial<KnowledgeDosage>) =>
        setAlt({ dosages_recommandes: alt.dosages_recommandes.map((row, i) => (i === index ? { ...row, ...patch } : row)) });
    const setMaterial = (index: number, patch: Partial<KnowledgeMaterial>) =>
        setAlt({ materiaux_recommandes: alt.materiaux_recommandes.map((row, i) => (i === index ? { ...row, ...patch } : row)) });

    return (
        <fieldset disabled={readOnly} className="space-y-4">
            <Field label="Titre de la fiche" htmlFor="fiche-titre">
                <input
                    id="fiche-titre"
                    className={fieldClass}
                    value={alt.titre_vulgarise}
                    onChange={(e) => setAlt({ titre_vulgarise: e.target.value })}
                />
            </Field>

            <Field label="Méthode d'exécution" htmlFor="fiche-methode">
                <textarea
                    id="fiche-methode"
                    rows={5}
                    className={fieldClass}
                    value={alt.methode_execution}
                    onChange={(e) => setAlt({ methode_execution: e.target.value })}
                />
            </Field>

            <Field label="Argumentaire pour le client" htmlFor="fiche-argumentaire" hint="Facultatif. Ce que l'artisan peut dire au propriétaire.">
                <textarea
                    id="fiche-argumentaire"
                    rows={3}
                    className={fieldClass}
                    value={alt.bouclier_autorite}
                    onChange={(e) => setAlt({ bouclier_autorite: e.target.value })}
                />
            </Field>

            <div>
                <p className="mb-1 text-xs font-semibold text-[var(--admin-text)]">Dosages</p>
                {alt.dosages_recommandes.length === 0 ? (
                    <p className="text-xs italic text-[var(--admin-muted)]">Aucun dosage dans cette fiche.</p>
                ) : null}
                <div className="space-y-2">
                    {alt.dosages_recommandes.map((row, index) => (
                        <div key={index} className="grid gap-2 md:grid-cols-[2fr_2fr_2fr_auto]">
                            <input
                                aria-label={`Élément du dosage ${index + 1}`}
                                placeholder="Élément"
                                className={fieldClass}
                                value={row.element}
                                onChange={(e) => setDosage(index, { element: e.target.value })}
                            />
                            <input
                                aria-label={`Quantité du dosage ${index + 1}`}
                                placeholder="Quantité"
                                className={fieldClass}
                                value={row.ratio}
                                onChange={(e) => setDosage(index, { ratio: e.target.value })}
                            />
                            <input
                                aria-label={`Unité de chantier du dosage ${index + 1}`}
                                placeholder="Unité de chantier"
                                className={fieldClass}
                                value={row.unite_mesure_locale}
                                onChange={(e) => setDosage(index, { unite_mesure_locale: e.target.value })}
                            />
                            <button
                                type="button"
                                className={actionButtonClass('secondary')}
                                onClick={() => setAlt({ dosages_recommandes: alt.dosages_recommandes.filter((_, i) => i !== index) })}
                            >
                                Retirer
                            </button>
                        </div>
                    ))}
                </div>
                {!readOnly ? (
                    <button
                        type="button"
                        className={`${actionButtonClass('secondary')} mt-2`}
                        onClick={() =>
                            setAlt({ dosages_recommandes: [...alt.dosages_recommandes, { element: '', ratio: '', unite_mesure_locale: '' }] })
                        }
                    >
                        Ajouter un dosage
                    </button>
                ) : null}
            </div>

            <div>
                <p className="mb-1 text-xs font-semibold text-[var(--admin-text)]">Matériaux</p>
                {alt.materiaux_recommandes.length === 0 ? (
                    <p className="text-xs italic text-[var(--admin-muted)]">Aucun matériau dans cette fiche.</p>
                ) : null}
                <div className="space-y-2">
                    {alt.materiaux_recommandes.map((row, index) => (
                        <div key={index} className="grid gap-2 md:grid-cols-[2fr_2fr_2fr_auto]">
                            <input
                                aria-label={`Nom du matériau ${index + 1}`}
                                placeholder="Matériau"
                                className={fieldClass}
                                value={row.nom}
                                onChange={(e) => setMaterial(index, { nom: e.target.value })}
                            />
                            <input
                                aria-label={`Substitut du matériau ${index + 1}`}
                                placeholder="Substitut acceptable"
                                className={fieldClass}
                                value={row.substitut_acceptable}
                                onChange={(e) => setMaterial(index, { substitut_acceptable: e.target.value })}
                            />
                            <input
                                aria-label={`Disponibilité du matériau ${index + 1}`}
                                placeholder="Où le trouver"
                                className={fieldClass}
                                value={row.disponibilite}
                                onChange={(e) => setMaterial(index, { disponibilite: e.target.value })}
                            />
                            <button
                                type="button"
                                className={actionButtonClass('secondary')}
                                onClick={() => setAlt({ materiaux_recommandes: alt.materiaux_recommandes.filter((_, i) => i !== index) })}
                            >
                                Retirer
                            </button>
                        </div>
                    ))}
                </div>
                {!readOnly ? (
                    <button
                        type="button"
                        className={`${actionButtonClass('secondary')} mt-2`}
                        onClick={() =>
                            setAlt({
                                materiaux_recommandes: [...alt.materiaux_recommandes, { nom: '', substitut_acceptable: '', disponibilite: '' }],
                            })
                        }
                    >
                        Ajouter un matériau
                    </button>
                ) : null}
            </div>

            <div className="grid gap-3 md:grid-cols-2">
                <Field
                    label="Mots-clés de recherche"
                    htmlFor="fiche-mots-cles"
                    hint="Séparés par des virgules. La fiche est trouvée par ces mots-clés."
                >
                    <input
                        id="fiche-mots-cles"
                        className={fieldClass}
                        value={sheet.metadata.tags_pathologies.join(', ')}
                        onChange={(e) =>
                            setMeta({
                                tags_pathologies: e.target.value
                                    .split(',')
                                    .map((tag) => tag.trimStart())
                                    .filter((tag, index, all) => tag !== '' || index === all.length - 1),
                            })
                        }
                    />
                </Field>
                <Field label="Type d'ouvrage" htmlFor="fiche-type-ouvrage">
                    <input
                        id="fiche-type-ouvrage"
                        className={fieldClass}
                        value={sheet.metadata.type_ouvrage}
                        onChange={(e) => setMeta({ type_ouvrage: e.target.value })}
                    />
                </Field>
            </div>

            <div className="grid gap-3 md:grid-cols-2">
                <Field label="Gamme de prix" htmlFor="fiche-gamme" hint="Laissez vide si le document ne donne aucun prix.">
                    <select
                        id="fiche-gamme"
                        className={fieldClass}
                        value={sheet.cout_estime_local.gamme_prix}
                        onChange={(e) => setCout({ gamme_prix: e.target.value })}
                    >
                        <option value="">Non renseignée</option>
                        <option value="Faible">Faible</option>
                        <option value="Moyen">Moyenne</option>
                        <option value="Eleve">Élevée</option>
                    </select>
                </Field>
                <Field label="Estimation du coût" htmlFor="fiche-cout">
                    <input
                        id="fiche-cout"
                        className={fieldClass}
                        value={sheet.cout_estime_local.estimation_m2_fcfa}
                        onChange={(e) => setCout({ estimation_m2_fcfa: e.target.value })}
                    />
                </Field>
            </div>

            <div className="rounded-2xl border border-[var(--admin-border)] p-3">
                <p className="mb-2 text-xs font-semibold text-[var(--admin-text)]">Document d'origine</p>
                <div className="grid gap-3 md:grid-cols-3">
                    <Field label="Organisme" htmlFor="fiche-source">
                        <input
                            id="fiche-source"
                            className={fieldClass}
                            value={sheet.norme_origine.source}
                            onChange={(e) => setNorme({ source: e.target.value })}
                        />
                    </Field>
                    <Field label="Référence" htmlFor="fiche-reference">
                        <input
                            id="fiche-reference"
                            className={fieldClass}
                            value={sheet.norme_origine.reference_article}
                            onChange={(e) => setNorme({ reference_article: e.target.value })}
                        />
                    </Field>
                    <Field label="Titre d'origine" htmlFor="fiche-titre-origine">
                        <input
                            id="fiche-titre-origine"
                            className={fieldClass}
                            value={sheet.norme_origine.titre_original}
                            onChange={(e) => setNorme({ titre_original: e.target.value })}
                        />
                    </Field>
                </div>
                <div className="mt-3">
                    <Field
                        label="Passage du document"
                        htmlFor="fiche-passage"
                        hint="Comparez ce passage aux valeurs de la fiche avant de l'approuver."
                    >
                        <textarea
                            id="fiche-passage"
                            rows={4}
                            className={fieldClass}
                            value={sheet.norme_origine.texte_brut}
                            onChange={(e) => setNorme({ texte_brut: e.target.value })}
                        />
                    </Field>
                </div>
            </div>
        </fieldset>
    );
}

/** Mots-clés nettoyés avant envoi : la saisie tolère une virgule en cours de frappe. */
export function cleanSheet(sheet: KnowledgeSheet): KnowledgeSheet {
    return {
        ...sheet,
        metadata: {
            ...sheet.metadata,
            tags_pathologies: sheet.metadata.tags_pathologies.map((tag) => tag.trim()).filter((tag) => tag !== ''),
        },
    };
}
