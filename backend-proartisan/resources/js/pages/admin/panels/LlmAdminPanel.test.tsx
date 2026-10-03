import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { get, post, put, del } = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), put: vi.fn(), del: vi.fn() }));

vi.mock('axios', () => ({ default: { get, post, put, delete: del } }));

import LlmAdminPanel from './LlmAdminPanel';

function sheet(overrides: Record<string, unknown> = {}) {
    return {
        id: 'fiche-1',
        norme_origine: { source: 'LBTP', reference_article: '3.1', titre_original: 'Dosage des bétons', texte_brut: 'Dosé à 350 kg/m³.' },
        alternative_prosartisan: {
            titre_vulgarise: 'Béton de dalle',
            methode_execution: 'Mélanger à sec puis ajouter l’eau.',
            bouclier_autorite: '',
            dosages_recommandes: [{ element: 'Ciment', ratio: '350 kg/m³', unite_mesure_locale: '7 sacs' }],
            materiaux_recommandes: [],
        },
        cout_estime_local: { gamme_prix: '', estimation_m2_fcfa: '', justification_economique: '' },
        metadata: { tags_pathologies: ['dosage_beton'], type_ouvrage: 'Dallage' },
        ...overrides,
    };
}

const importRow = {
    id: 'doc-1',
    filename: 'guide-lbtp.pdf',
    file_size: 2 * 1024 * 1024,
    imported_at: '2026-10-03 10:00:00',
    status: 'en_attente',
    status_label: 'En attente',
    error_message: null,
    sheets_count: 0,
    has_file: true,
};

const stagingRow = {
    id: 'fiche-1',
    source: 'guide-lbtp.pdf',
    status: 'PENDING',
    status_label: 'À relire',
    origin: 'ia',
    origin_label: 'Générée par IA',
    reviewer_notes: null,
    generated_json: sheet(),
};

function serve({ imports = [] as unknown[], staging = [] as unknown[], production = [] as unknown[] } = {}) {
    get.mockImplementation((url: string) => {
        if (url.endsWith('/imports')) return Promise.resolve({ data: imports });
        if (url.endsWith('/staging')) return Promise.resolve({ data: staging });

        return Promise.resolve({ data: production });
    });
}

beforeEach(() => {
    [get, post, put, del].forEach((mock) => mock.mockReset());
    post.mockResolvedValue({ data: {} });
    put.mockResolvedValue({ data: {} });
    del.mockResolvedValue({ data: {} });
});

describe('LlmAdminPanel', () => {
    it('annonce une base vide sans proposer de document de démonstration', async () => {
        serve();
        render(<LlmAdminPanel />);

        expect(await screen.findByText('Aucun document importé')).toBeInTheDocument();
        expect(screen.getByText('Aucune fiche à relire')).toBeInTheDocument();
        expect(screen.getByText('Aucune fiche publiée')).toBeInTheDocument();
        expect(screen.queryByText(/Guide LBTP 2023/)).not.toBeInTheDocument();
    });

    it('annonce une panne de chargement au lieu d’une base vide', async () => {
        get.mockRejectedValue({ response: { status: 500 } });
        render(<LlmAdminPanel />);

        expect(await screen.findByRole('alert')).toHaveTextContent("n'a pas pu être chargée");
        expect(screen.queryByText('Aucun document importé')).not.toBeInTheDocument();
    });

    it('importe un document en envoyant le fichier au serveur', async () => {
        serve();
        render(<LlmAdminPanel />);
        await screen.findByText('Aucun document importé');

        const file = new File(['%PDF-1.4'], 'norme.pdf', { type: 'application/pdf' });
        fireEvent.change(screen.getByLabelText('Importer un document'), { target: { files: [file] } });

        await waitFor(() => expect(post).toHaveBeenCalledWith('/admin/api/llm/imports', expect.any(FormData)));
        expect((post.mock.calls[0][1] as FormData).get('document')).toBe(file);
        expect(await screen.findByRole('status')).toHaveTextContent('norme.pdf');
    });

    it('affiche le refus du serveur quand le document est rejeté', async () => {
        serve();
        post.mockRejectedValue({ response: { status: 422, data: { errors: { document: ['Format non accepté. Formats acceptés : pdf.'] } } } });
        render(<LlmAdminPanel />);
        await screen.findByText('Aucun document importé');

        fireEvent.change(screen.getByLabelText('Importer un document'), {
            target: { files: [new File(['x'], 'script.php', { type: 'text/plain' })] },
        });

        expect(await screen.findByRole('alert')).toHaveTextContent('Format non accepté');
    });

    it('lance la génération des fiches d’un document', async () => {
        serve({ imports: [importRow] });
        render(<LlmAdminPanel />);

        fireEvent.click(await screen.findByRole('button', { name: 'Générer les fiches' }));

        await waitFor(() => expect(post).toHaveBeenCalledWith('/admin/api/llm/imports/doc-1/generate'));
    });

    it('montre le motif d’un échec de génération et bloque le bouton pendant une génération', async () => {
        serve({
            imports: [
                { ...importRow, status: 'echec', status_label: 'Échec', error_message: "L'analyse du document par l'IA n'a pas abouti." },
                { ...importRow, id: 'doc-2', filename: 'autre.pdf', status: 'en_cours', status_label: 'Génération en cours' },
            ],
        });
        render(<LlmAdminPanel />);

        expect(await screen.findByText("L'analyse du document par l'IA n'a pas abouti.")).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Génération…' })).toBeDisabled();
    });

    it('signale qu’une fiche vient de l’IA et l’ouvre pour relecture', async () => {
        serve({ staging: [stagingRow] });
        render(<LlmAdminPanel />);

        fireEvent.click(await screen.findByRole('button', { name: 'Relire' }));

        const form = screen.getByRole('form', { name: 'Relecture de la fiche' });
        expect(within(form).getByText('Générée par IA — à relire')).toBeInTheDocument();
        expect(within(form).getByLabelText('Titre de la fiche')).toHaveValue('Béton de dalle');
        expect(within(form).getByLabelText('Passage du document')).toHaveValue('Dosé à 350 kg/m³.');
    });

    it('enregistre les corrections puis publie après confirmation', async () => {
        serve({ staging: [stagingRow] });
        render(<LlmAdminPanel />);

        fireEvent.click(await screen.findByRole('button', { name: 'Relire' }));
        fireEvent.change(screen.getByLabelText('Titre de la fiche'), { target: { value: 'Béton de dalle corrigé' } });
        fireEvent.click(screen.getByRole('button', { name: 'Approuver et publier' }));

        const dialog = await screen.findByRole('dialog');
        expect(dialog).toHaveTextContent('Vérifiez chaque dosage');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Approuver et publier' }));

        await waitFor(() => expect(post).toHaveBeenCalledWith('/admin/api/llm/staging/fiche-1/approve'));
        expect(put).toHaveBeenCalledWith(
            '/admin/api/llm/staging/fiche-1',
            expect.objectContaining({
                alternative_prosartisan: expect.objectContaining({ titre_vulgarise: 'Béton de dalle corrigé' }),
            }),
        );
        expect(put.mock.invocationCallOrder[0]).toBeLessThan(post.mock.invocationCallOrder[0]);
    });

    it('ne publie rien si la confirmation est annulée', async () => {
        serve({ staging: [stagingRow] });
        render(<LlmAdminPanel />);

        fireEvent.click(await screen.findByRole('button', { name: 'Relire' }));
        fireEvent.click(screen.getByRole('button', { name: 'Approuver et publier' }));
        const dialog = await screen.findByRole('dialog');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Annuler' }));

        await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
        expect(post).not.toHaveBeenCalled();
        expect(put).not.toHaveBeenCalled();
    });

    it('exige un motif pour rejeter une fiche', async () => {
        serve({ staging: [stagingRow] });
        render(<LlmAdminPanel />);

        fireEvent.click(await screen.findByRole('button', { name: 'Rejeter' }));
        const dialog = await screen.findByRole('dialog');
        const confirmButton = within(dialog).getByRole('button', { name: 'Rejeter' });
        expect(confirmButton).toBeDisabled();

        fireEvent.change(within(dialog).getByRole('textbox'), { target: { value: 'Dosage contraire à la norme' } });
        fireEvent.click(confirmButton);

        await waitFor(() =>
            expect(post).toHaveBeenCalledWith('/admin/api/llm/staging/fiche-1/reject', { reviewer_notes: 'Dosage contraire à la norme' }),
        );
    });

    it('crée une fiche manuelle sans choisir son identifiant', async () => {
        serve();
        post.mockResolvedValue({ data: { id: 'fiche-9' } });
        render(<LlmAdminPanel />);
        await screen.findByText('Aucune fiche à relire');

        fireEvent.click(screen.getByRole('button', { name: 'Nouvelle fiche manuelle' }));
        fireEvent.change(screen.getByLabelText('Titre de la fiche'), { target: { value: 'Enduit trois couches' } });
        fireEvent.change(screen.getByLabelText('Mots-clés de recherche'), { target: { value: 'enduit, facade, ' } });
        fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

        await waitFor(() => expect(post).toHaveBeenCalled());
        const [url, payload] = post.mock.calls[0];
        expect(url).toBe('/admin/api/llm/staging');
        expect(payload).not.toHaveProperty('id');
        expect(payload.metadata.tags_pathologies).toEqual(['enduit', 'facade']);
    });

    it('retire une fiche publiée avec un motif', async () => {
        serve({ production: [sheet()] });
        render(<LlmAdminPanel />);

        fireEvent.click(await screen.findByRole('button', { name: 'Retirer' }));
        const dialog = await screen.findByRole('dialog');
        fireEvent.change(within(dialog).getByRole('textbox'), { target: { value: 'Norme remplacée' } });
        fireEvent.click(within(dialog).getByRole('button', { name: 'Retirer' }));

        await waitFor(() =>
            expect(post).toHaveBeenCalledWith('/admin/api/llm/production/fiche-1/withdraw', { reason: 'Norme remplacée' }),
        );
    });

    it('interroge le vrai Assistant et dit quand aucune fiche n’est citée', async () => {
        serve();
        post.mockResolvedValue({ data: { response: 'Aucune fiche validée ne traite ce sujet.', sources: [] } });
        render(<LlmAdminPanel />);
        await screen.findByText('Aucun document importé');

        fireEvent.change(screen.getByLabelText("Question à l'Assistant"), { target: { value: 'dosage dalle' } });
        fireEvent.click(screen.getByRole('button', { name: 'Envoyer' }));

        await waitFor(() => expect(post).toHaveBeenCalledWith('/admin/api/llm/chat', { message: 'dosage dalle' }));
        expect(await screen.findByText('Aucune fiche validée ne traite ce sujet.')).toBeInTheDocument();
        expect(screen.getByText('Aucune fiche publiée citée.')).toBeInTheDocument();
    });
});
