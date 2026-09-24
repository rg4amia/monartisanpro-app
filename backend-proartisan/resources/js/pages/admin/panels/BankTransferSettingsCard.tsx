// Coordonnées de virement bancaire affichées aux payeurs (obligatoire au-delà
// du plafond Mobile Money). Jamais écrites dans le code : tant qu'elles ne sont
// pas renseignées ici, le virement est refusé plutôt que dirigé vers un compte
// inventé (Règle d'or 29). L'IBAN est validé côté serveur (clé de contrôle).

import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { SectionTitle, Surface } from '../shared';

export interface BankTransferSettings {
    bank_name: string;
    account_name: string;
    iban: string;
}

interface BankTransferSettingsCardProps {
    settings: BankTransferSettings;
}

const FIELDS: Array<{ name: keyof BankTransferSettings; label: string; placeholder: string }> = [
    { name: 'bank_name', label: 'Banque', placeholder: 'Ex. Banque Atlantique Côte d’Ivoire' },
    { name: 'account_name', label: 'Titulaire du compte', placeholder: 'Ex. PROSARTISAN SÉQUESTRE' },
    { name: 'iban', label: 'IBAN', placeholder: 'CI93 CI00 8011 1301 1342 9120 0589' },
];

export function BankTransferSettingsCard({ settings }: BankTransferSettingsCardProps) {
    const { data, setData, put, processing, errors, recentlySuccessful } = useForm<BankTransferSettings>({
        bank_name: settings.bank_name ?? '',
        account_name: settings.account_name ?? '',
        iban: settings.iban ?? '',
    });

    const configured = Boolean(settings.bank_name && settings.account_name && settings.iban);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        put('/admin/settings/bank-transfer', { preserveScroll: true });
    };

    return (
        <Surface className="rounded-[32px] p-5 lg:p-6 xl:col-span-2">
            <SectionTitle
                description="Communiquées aux clients et recruteurs qui paient par virement (obligatoire au-delà du plafond Mobile Money). Chaque modification est journalisée."
                title="Coordonnées de virement bancaire"
            />

            {!configured ? (
                <p role="alert" className="mt-4 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Coordonnées non renseignées : le paiement par virement est actuellement refusé, y compris pour les montants au-delà du plafond Mobile Money.
                </p>
            ) : null}

            <form onSubmit={submit} className="mt-5 grid gap-4 md:grid-cols-3">
                {FIELDS.map((field) => (
                    <label key={field.name} className="block">
                        <span className="mb-1.5 block text-xs font-semibold text-[var(--admin-muted)]">{field.label}</span>
                        <input
                            type="text"
                            name={field.name}
                            value={data[field.name]}
                            onChange={(e) => setData(field.name, e.target.value)}
                            placeholder={field.placeholder}
                            className="admin-input w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm outline-none"
                        />
                        {errors[field.name] ? <span className="mt-1 block text-xs text-rose-600">{errors[field.name]}</span> : null}
                    </label>
                ))}

                <div className="flex items-center gap-3 md:col-span-3">
                    <button type="submit" disabled={processing} className="admin-button admin-button--primary disabled:opacity-50">
                        {processing ? 'Enregistrement…' : 'Enregistrer les coordonnées'}
                    </button>
                    {recentlySuccessful ? <span className="text-sm text-green-700">Coordonnées enregistrées.</span> : null}
                </div>
            </form>
        </Surface>
    );
}
