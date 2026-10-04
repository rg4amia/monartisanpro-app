import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const routerPost = vi.fn();

vi.mock('@inertiajs/react', () => ({
    router: {
        post: (...args: unknown[]) => routerPost(...args),
    },
}));

import RolesPermissionsPanel from './roles-permissions-panel';

const catalog = {
    users: {
        'admin.users.view': 'Consulter les comptes utilisateurs',
        'admin.users.manage': 'Créer, modifier, suspendre un compte',
    },
    plateforme: {
        'admin.roles.manage': 'Gérer les rôles et les droits des administrateurs',
        'admin.faq.manage': "Gérer la FAQ d'aide et support",
    },
};

const allCapabilities = ['admin.users.view', 'admin.users.manage', 'admin.roles.manage', 'admin.faq.manage'];

const permissions = [
    { id: 1, name: 'mission.create', description: 'Créer une mission', category: 'missions' },
    { id: 2, name: 'litige.arbitrate', description: 'Arbitrer un litige', category: 'litiges' },
];

function makeAdmin(overrides: Record<string, unknown> = {}) {
    return {
        id: 7,
        name: 'Awa Admin',
        email: 'awa@example.test',
        phone: '+2250700000007',
        capabilities: ['admin.faq.manage'],
        protected: false,
        is_self: false,
        ...overrides,
    };
}

function renderPanel(admin = makeAdmin(), extra: Record<string, unknown> = {}) {
    render(
        <RolesPermissionsPanel
            allPermissions={permissions}
            rolesPermissions={{ client: ['mission.create'], artisan: [], fournisseur: [], referent: [], livreur: [] }}
            adminCapabilityCatalog={catalog}
            admins={[admin]}
            protectedRolePermissions={{ client: ['mission.create'] }}
            reservedRolePermissions={{ 'litige.arbitrate': ['referent'] }}
            {...extra}
        />,
    );
}

/** Case à cocher d'une capacité du backoffice, retrouvée par son nom technique. */
function capabilityCheckbox(name: string): HTMLInputElement {
    return screen.getByText(name).closest('label')!.querySelector('input[type="checkbox"]') as HTMLInputElement;
}

function fullAccessCheckbox(): HTMLInputElement {
    return screen.getAllByText('Accès total').map((node) => node.closest('label')).find((label) => label !== null)!.querySelector('input[type="checkbox"]') as HTMLInputElement;
}

describe('RolesPermissionsPanel — droits des administrateurs', () => {
    beforeEach(() => {
        routerPost.mockReset();
    });

    it("refuse de retirer la dernière capacité d'un administrateur", () => {
        renderPanel();

        fireEvent.click(capabilityCheckbox('admin.faq.manage'));

        expect(routerPost).not.toHaveBeenCalled();
        expect(screen.getByRole('alert')).toHaveTextContent("Cochez au moins une capacité, ou l'accès total.");
    });

    it("n'impose pas la gestion des rôles au compte d'un autre administrateur", () => {
        renderPanel();

        fireEvent.click(capabilityCheckbox('admin.users.manage'));

        expect(routerPost).toHaveBeenCalledWith(
            '/admin/admins/7/permissions',
            { capabilities: ['admin.faq.manage', 'admin.users.manage'] },
            expect.anything(),
        );
    });

    it("garde la gestion des rôles et la consultation des utilisateurs sur son propre compte", () => {
        renderPanel(makeAdmin({ is_self: true, capabilities: ['admin.roles.manage', 'admin.users.view', 'admin.faq.manage'] }));

        fireEvent.click(capabilityCheckbox('admin.roles.manage'));

        const sent = routerPost.mock.calls[0][1].capabilities as string[];
        expect(sent).toContain('admin.roles.manage');
        expect(sent).toContain('admin.users.view');
    });

    it("retirer l'accès total écrit chaque capacité du catalogue", () => {
        renderPanel(makeAdmin({ capabilities: ['*'] }));

        fireEvent.click(fullAccessCheckbox());

        expect(routerPost).toHaveBeenCalledWith(
            '/admin/admins/7/permissions',
            { capabilities: allCapabilities },
            expect.anything(),
        );
    });

    it("décocher une capacité d'un compte en accès total garde toutes les autres", () => {
        renderPanel(makeAdmin({ capabilities: ['*'] }));

        fireEvent.click(capabilityCheckbox('admin.faq.manage'));

        expect(routerPost.mock.calls[0][1]).toEqual({
            capabilities: ['admin.users.view', 'admin.users.manage', 'admin.roles.manage'],
        });
    });

    it('rétablit la sentinelle quand toutes les capacités sont cochées', () => {
        renderPanel(makeAdmin({ capabilities: ['admin.users.view', 'admin.users.manage', 'admin.roles.manage'] }));

        fireEvent.click(capabilityCheckbox('admin.faq.manage'));

        expect(routerPost.mock.calls[0][1]).toEqual({ capabilities: ['admin.full-access'] });
    });

    it('verrouille les cases du super administrateur protégé', () => {
        renderPanel(makeAdmin({ protected: true, capabilities: ['*'] }));

        expect(screen.getByText('Super administrateur protégé.')).toBeInTheDocument();
        expect(fullAccessCheckbox()).toBeDisabled();
        expect(capabilityCheckbox('admin.faq.manage')).toBeDisabled();
    });

    it('affiche le message renvoyé par le serveur en cas de refus', () => {
        routerPost.mockImplementation((...args: unknown[]) => {
            const options = args[2] as { onError: (errors: Record<string, string>) => void; onFinish: () => void };
            options.onError({ capabilities: "Cochez au moins une capacité, ou l'accès total." });
            options.onFinish();
        });
        renderPanel();

        fireEvent.click(capabilityCheckbox('admin.users.manage'));

        expect(screen.getByRole('alert')).toHaveTextContent("Cochez au moins une capacité, ou l'accès total.");
    });
});

describe('RolesPermissionsPanel — droits des rôles de l’application', () => {
    beforeEach(() => {
        routerPost.mockReset();
    });

    it('ne propose ni le rôle administrateur ni un rôle sans effet', () => {
        renderPanel();

        for (const role of ['Client', 'Artisan', 'Fournisseur', 'Référent', 'Livreur']) {
            expect(screen.getByRole('button', { name: role })).toBeInTheDocument();
        }
        expect(screen.queryByRole('button', { name: /Administrateur/ })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /driver/i })).not.toBeInTheDocument();
    });

    it('ne laisse pas retirer une action indispensable au rôle', () => {
        renderPanel();

        expect(screen.getByRole('checkbox', { name: 'mission.create' })).toBeDisabled();
        expect(screen.getByText('Indispensable à ce rôle : ne se retire pas.')).toBeInTheDocument();
    });

    it("ne montre pas à un rôle une action réservée à un autre", () => {
        renderPanel();

        expect(screen.queryByRole('checkbox', { name: 'litige.arbitrate' })).not.toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Référent' }));
        expect(screen.getByRole('checkbox', { name: 'litige.arbitrate' })).toBeEnabled();
    });

    it("annonce un rôle sans action réglable au lieu d'un cadre vide", () => {
        renderPanel(makeAdmin(), { reservedRolePermissions: { 'mission.create': ['client'], 'litige.arbitrate': ['referent'] } });

        fireEvent.click(screen.getByRole('button', { name: 'Livreur' }));

        expect(screen.getByText("Aucune action réglable pour ce rôle : son accès est fixé par l'application.")).toBeInTheDocument();
        expect(screen.queryByRole('checkbox', { name: 'mission.create' })).not.toBeInTheDocument();
    });

    it('attribue une action au rôle sélectionné', () => {
        renderPanel();

        fireEvent.click(screen.getByRole('button', { name: 'Artisan' }));
        fireEvent.click(screen.getByRole('checkbox', { name: 'mission.create' }));

        expect(routerPost).toHaveBeenCalledWith(
            '/admin/roles-permissions/assign',
            { role: 'artisan', permission: 'mission.create' },
            expect.anything(),
        );
    });

    it("ne propose le retour aux droits d'origine que pour un rôle modifié", async () => {
        renderPanel(makeAdmin(), { customizedRoles: { client: true, artisan: false } });

        expect(screen.getByText("Les droits de ce rôle ont été modifiés par rapport à leur réglage d'origine.")).toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: "Rétablir les droits d'origine" }));

        const dialog = await screen.findByRole('dialog');
        expect(dialog).toHaveTextContent("Rétablir les droits d'origine du rôle Client ?");
        expect(routerPost).not.toHaveBeenCalled();

        fireEvent.click(within(dialog).getByRole('button', { name: "Rétablir les droits d'origine" }));
        await waitFor(() =>
            expect(routerPost).toHaveBeenCalledWith('/admin/roles-permissions/reset', { role: 'client' }, expect.anything()),
        );

        fireEvent.click(screen.getByRole('button', { name: 'Artisan' }));
        expect(screen.queryByRole('button', { name: "Rétablir les droits d'origine" })).not.toBeInTheDocument();
    });
});

describe('RolesPermissionsPanel — profils types', () => {
    const profiles = {
        support: { label: 'Support', description: 'Comptes et FAQ', capabilities: ['admin.users.view', 'admin.faq.manage'] },
    };

    beforeEach(() => {
        routerPost.mockReset();
    });

    it('applique un profil après confirmation', async () => {
        renderPanel(makeAdmin(), { adminProfiles: profiles });

        fireEvent.click(screen.getByRole('button', { name: 'Support' }));
        const dialog = await screen.findByRole('dialog');
        expect(dialog).toHaveTextContent('Appliquer le profil « Support » à Awa Admin ?');
        expect(routerPost).not.toHaveBeenCalled();

        fireEvent.click(within(dialog).getByRole('button', { name: 'Appliquer le profil' }));
        await waitFor(() =>
            expect(routerPost).toHaveBeenCalledWith(
                '/admin/admins/7/permissions',
                { capabilities: ['admin.users.view', 'admin.faq.manage'] },
                expect.anything(),
            ),
        );
    });

    it('garde la gestion des rôles quand on applique un profil à son propre compte', async () => {
        renderPanel(makeAdmin({ is_self: true }), { adminProfiles: profiles });

        fireEvent.click(screen.getByRole('button', { name: 'Support' }));
        fireEvent.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Appliquer le profil' }));

        await waitFor(() => expect(routerPost).toHaveBeenCalled());
        const sent = (routerPost.mock.calls[0][1] as { capabilities: string[] }).capabilities;
        expect(sent).toEqual(expect.arrayContaining(['admin.roles.manage', 'admin.users.view', 'admin.faq.manage']));
    });

    it('ne propose aucun profil pour un super administrateur protégé', () => {
        renderPanel(makeAdmin({ protected: true, capabilities: ['*'] }), { adminProfiles: profiles });

        expect(screen.queryByRole('button', { name: 'Support' })).not.toBeInTheDocument();
    });
});
