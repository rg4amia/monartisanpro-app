import { router } from '@inertiajs/react';
import axios from 'axios';
import React, { useState } from 'react';

interface Stats {
    total_cost: number;
    total_requests: number;
    avg_response_time: number;
    success_rate: number;
}

interface CostByModel {
    model_name: string;
    cost: string | number;
    count: number;
}

interface DailyUsage {
    date: string;
    cost: string | number;
    tokens: number;
    requests: number;
}

interface LogItem {
    id: number;
    user_id: number | null;
    user_email: string | null;
    model_name: string;
    action_type: string;
    prompt_tokens: number;
    completion_tokens: number;
    total_tokens: number;
    response_time_ms: number;
    status_code: number;
    error_message: string | null;
    estimated_cost_usd: string | number;
    created_at: string;
}

interface AiDashboardPanelProps {
    stats: Stats;
    costsByModel: CostByModel[];
    dailyUsage: DailyUsage[];
    logs: LogItem[];
    settings: Record<string, string>;
}

export default function AiDashboardPanel({ stats, costsByModel, dailyUsage, logs, settings }: AiDashboardPanelProps) {
    const [dailyLimit, setDailyLimit] = useState(settings.daily_user_limit || '20');
    const [monthlyLimit, setMonthlyLimit] = useState(settings.monthly_user_limit || '0');
    const [aiEnabled, setAiEnabled] = useState(settings.ai_enabled === '1');
    const [isSaving, setIsSaving] = useState(false);
    const [successMessage, setSuccessMessage] = useState<string | null>(null);

    const handleSaveSettings = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsSaving(true);
        setSuccessMessage(null);

        try {
            await axios.post('/admin/ai-dashboard/settings', {
                daily_user_limit: parseInt(dailyLimit, 10),
                monthly_user_limit: parseInt(monthlyLimit, 10) || 0,
                ai_enabled: aiEnabled ? '1' : '0'
            });
            setSuccessMessage('Paramètres IA mis à jour avec succès.');
            setTimeout(() => setSuccessMessage(null), 3000);
            router.reload({ only: ['settings'] });
        } catch (error) {
            console.error('Failed to save settings', error);
        } finally {
            setIsSaving(false);
        }
    };

    return (
        <div className="space-y-6">
            {/* Header section */}
            <div className="flex flex-col md:flex-row md:items-center md:justify-between border-b border-[var(--admin-border)] pb-4">
                <div>
                    <h2 className="text-xl font-semibold text-[var(--admin-text)]">Suivi et Contrôle de l'IA</h2>
                    <p className="text-sm text-[var(--admin-muted)]">
                        Visualisez la consommation de tokens, le temps de réponse et configurez les limites d'accès.
                    </p>
                </div>
            </div>

            {/* Metrics cards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {/* Cost */}
                <div className="bg-[var(--admin-panel-strong)] p-5 rounded-xl border border-[var(--admin-border)] shadow-sm">
                    <div className="text-xs font-semibold text-[var(--admin-muted)] uppercase tracking-wider">Coût total estimé</div>
                    <div className="mt-2 text-2xl font-bold text-[var(--admin-text)]">
                        ${stats.total_cost.toFixed(4)}
                    </div>
                    <p className="mt-1 text-sm font-semibold text-indigo-600">
                        ~{Math.round(stats.total_cost * 600).toLocaleString('fr-FR')} FCFA
                    </p>
                    <p className="mt-0.5 text-[10px] text-[var(--admin-muted)]">Sur la base de 1 USD = 600 FCFA</p>
                </div>

                {/* Requests */}
                <div className="bg-[var(--admin-panel-strong)] p-5 rounded-xl border border-[var(--admin-border)] shadow-sm">
                    <div className="text-xs font-semibold text-[var(--admin-muted)] uppercase tracking-wider">Appels totaux</div>
                    <div className="mt-2 text-2xl font-bold text-[var(--admin-text)]">
                        {stats.total_requests.toLocaleString('fr-FR')}
                    </div>
                    <p className="mt-1 text-xs text-[var(--admin-muted)]">Requêtes Gemini & recherches vectorielles</p>
                </div>

                {/* Response time */}
                <div className="bg-[var(--admin-panel-strong)] p-5 rounded-xl border border-[var(--admin-border)] shadow-sm">
                    <div className="text-xs font-semibold text-[var(--admin-muted)] uppercase tracking-wider">Temps de réponse moyen</div>
                    <div className="mt-2 text-2xl font-bold text-[var(--admin-text)]">
                        {(stats.avg_response_time / 1000).toFixed(2)}s
                    </div>
                    <p className="mt-1 text-xs text-[var(--admin-muted)]">{stats.avg_response_time.toFixed(0)} ms en moyenne</p>
                </div>

                {/* Success rate */}
                <div className="bg-[var(--admin-panel-strong)] p-5 rounded-xl border border-[var(--admin-border)] shadow-sm">
                    <div className="text-xs font-semibold text-[var(--admin-muted)] uppercase tracking-wider">Taux de réussite</div>
                    <div className="mt-2 text-2xl font-bold text-emerald-600">
                        {stats.success_rate}%
                    </div>
                    <p className="mt-1 text-xs text-[var(--admin-muted)]">Pourcentage de réponses HTTP 200 OK</p>
                </div>
            </div>

            {/* Settings and cost by model */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Configuration panel */}
                <div className="bg-[var(--admin-panel-strong)] p-5 rounded-xl border border-[var(--admin-border)] shadow-sm lg:col-span-1">
                    <h3 className="font-semibold text-[var(--admin-text)] mb-4">Contrôle des quotas & Accès</h3>
                    <form onSubmit={handleSaveSettings} className="space-y-4">
                        {/* Enable/disable IA */}
                        <div className="flex items-center justify-between pb-2 border-b border-[var(--admin-border)]">
                            <div>
                                <label className="text-sm font-medium text-[var(--admin-text)]">Activer l'assistant IA</label>
                                <p className="text-xs text-[var(--admin-muted)]">Active ou coupe l'accès général.</p>
                            </div>
                            <input
                                type="checkbox"
                                checked={aiEnabled}
                                onChange={(e) => setAiEnabled(e.target.checked)}
                                className="w-9 h-5 bg-[var(--admin-border)] rounded-full appearance-none cursor-pointer checked:bg-indigo-600 relative before:content-[''] before:absolute before:w-4 before:h-4 before:rounded-full before:bg-white before:top-[2px] before:left-[2px] checked:before:translate-x-4 before:transition-transform duration-200"
                            />
                        </div>

                        {/* Daily limit */}
                        <div>
                            <label className="block text-sm font-medium text-[var(--admin-text)]">Limite quotidienne par utilisateur</label>
                            <div className="mt-1 flex rounded-md shadow-sm">
                                <input
                                    type="number"
                                    min="0"
                                    value={dailyLimit}
                                    onChange={(e) => setDailyLimit(e.target.value)}
                                    className="admin-input block w-full rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                            </div>
                            <p className="mt-1 text-xs text-[var(--admin-muted)]">Mettre 0 pour aucune limite journalière.</p>
                        </div>

                        {/* Monthly limit */}
                        <div>
                            <label className="block text-sm font-medium text-[var(--admin-text)]">Limite mensuelle par utilisateur</label>
                            <div className="mt-1 flex rounded-md shadow-sm">
                                <input
                                    type="number"
                                    min="0"
                                    value={monthlyLimit}
                                    onChange={(e) => setMonthlyLimit(e.target.value)}
                                    className="admin-input block w-full rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                            </div>
                            <p className="mt-1 text-xs text-[var(--admin-muted)]">Mettre 0 pour aucune limite mensuelle. Une surcharge par utilisateur reste prioritaire.</p>
                        </div>

                        {successMessage && (
                            <div className="text-xs text-emerald-700 bg-emerald-50 p-2 rounded">
                                {successMessage}
                            </div>
                        )}

                        <button
                            type="submit"
                            disabled={isSaving}
                            className="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-55"
                        >
                            {isSaving ? 'Sauvegarde...' : 'Enregistrer'}
                        </button>
                    </form>
                </div>

                {/* Models summary */}
                <div className="bg-[var(--admin-panel-strong)] p-5 rounded-xl border border-[var(--admin-border)] shadow-sm lg:col-span-2 flex flex-col justify-between">
                    <div>
                        <h3 className="font-semibold text-[var(--admin-text)] mb-4">Répartition des Coûts par Modèle</h3>
                        <div className="space-y-4">
                            {costsByModel.length === 0 ? (
                                <p className="text-sm text-[var(--admin-muted)]">Aucune donnée d'utilisation disponible.</p>
                            ) : (
                                costsByModel.map((item, index) => {
                                    const costVal = typeof item.cost === 'string' ? parseFloat(item.cost) : item.cost;
                                    const percent = stats.total_cost > 0 ? (costVal / stats.total_cost) * 100 : 0;
                                    return (
                                        <div key={index} className="space-y-1">
                                            <div className="flex justify-between text-sm">
                                                <span className="font-medium text-[var(--admin-text)]">{item.model_name}</span>
                                                <span className="text-[var(--admin-muted)]">
                                                    ${costVal.toFixed(4)} ({item.count} appels)
                                                </span>
                                            </div>
                                            <div className="w-full bg-[var(--admin-panel)] h-2 rounded-full overflow-hidden">
                                                <div
                                                    className="bg-indigo-600 h-2 rounded-full"
                                                    style={{ width: `${percent}%` }}
                                                ></div>
                                            </div>
                                        </div>
                                    );
                                })
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Daily Usage History (SVG Graph) */}
            <div className="bg-[var(--admin-panel-strong)] p-5 rounded-xl border border-[var(--admin-border)] shadow-sm">
                <h3 className="font-semibold text-[var(--admin-text)] mb-4">Évolution quotidienne des coûts IA</h3>
                {dailyUsage.length === 0 ? (
                    <p className="text-sm text-[var(--admin-muted)]">Historique insuffisant pour afficher le graphique.</p>
                ) : (
                    <div className="h-48 w-full flex items-end justify-between pt-4 space-x-2">
                        {dailyUsage.map((day, index) => {
                            const costVal = typeof day.cost === 'string' ? parseFloat(day.cost) : day.cost;
                            const maxCost = Math.max(...dailyUsage.map(d => typeof d.cost === 'string' ? parseFloat(d.cost) : d.cost), 0.001);
                            const heightPercent = (costVal / maxCost) * 100;
                            return (
                                <div key={index} className="flex-1 flex flex-col items-center group relative h-full justify-end">
                                    {/* Tooltip */}
                                    <div className="absolute bottom-full mb-1 hidden group-hover:block bg-slate-950 text-white text-[10px] rounded p-1 z-10 whitespace-nowrap shadow-lg">
                                        {day.date}<br />
                                        Coût: ${costVal.toFixed(5)}<br />
                                        Appels: {day.requests}<br />
                                        Tokens: {day.tokens.toLocaleString()}
                                    </div>
                                    <div
                                        className="w-full bg-indigo-500/80 hover:bg-indigo-600 rounded-t transition-all duration-300 min-h-[4px]"
                                        style={{ height: `${heightPercent}%` }}
                                    ></div>
                                    <span className="text-[9px] text-[var(--admin-muted)] mt-2 truncate w-full text-center">
                                        {day.date.substring(5)}
                                    </span>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>

            {/* Logs list */}
            <div className="bg-[var(--admin-panel-strong)] rounded-xl border border-[var(--admin-border)] shadow-sm overflow-hidden">
                <div className="p-5 border-b border-[var(--admin-border)]">
                    <h3 className="font-semibold text-[var(--admin-text)]">Dernières interactions IA</h3>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse">
                        <thead>
                            <tr className="bg-[var(--admin-panel)] text-xs font-semibold text-[var(--admin-muted)] uppercase border-b border-[var(--admin-border)]">
                                <th className="py-3 px-4">Date</th>
                                <th className="py-3 px-4">Utilisateur</th>
                                <th className="py-3 px-4">Modèle</th>
                                <th className="py-3 px-4">Action</th>
                                <th className="py-3 px-4 text-center">Statut</th>
                                <th className="py-3 px-4 text-right">Tokens</th>
                                <th className="py-3 px-4 text-right">Latence</th>
                                <th className="py-3 px-4 text-right">Coût</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[var(--admin-border)] text-sm">
                            {logs.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="py-6 text-center text-[var(--admin-muted)]">
                                        Aucun log disponible
                                    </td>
                                </tr>
                            ) : (
                                logs.map((log) => {
                                    const costVal = typeof log.estimated_cost_usd === 'string' ? parseFloat(log.estimated_cost_usd) : log.estimated_cost_usd;
                                    return (
                                        <tr key={log.id} className="hover:bg-[var(--admin-panel)]">
                                            <td className="py-3 px-4 text-xs text-[var(--admin-muted)] whitespace-nowrap">
                                                {new Date(log.created_at).toLocaleString('fr-FR')}
                                            </td>
                                            <td className="py-3 px-4 font-medium text-[var(--admin-text)] max-w-[150px] truncate">
                                                {log.user_email || 'Visiteur anonyme'}
                                            </td>
                                            <td className="py-3 px-4 text-[var(--admin-muted)] font-mono text-xs">
                                                {log.model_name.replace('models/', '')}
                                            </td>
                                            <td className="py-3 px-4 text-[var(--admin-muted)]">
                                                <span className="px-2 py-0.5 rounded-full text-xs font-semibold bg-[var(--admin-panel)]">
                                                    {log.action_type}
                                                </span>
                                            </td>
                                            <td className="py-3 px-4 text-center">
                                                <span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${
                                                    log.status_code === 200
                                                        ? 'bg-emerald-100 text-emerald-800'
                                                        : 'bg-rose-100 text-rose-800'
                                                }`}>
                                                    {log.status_code}
                                                </span>
                                            </td>
                                            <td className="py-3 px-4 text-right font-mono text-xs">
                                                {log.total_tokens.toLocaleString()}
                                            </td>
                                            <td className="py-3 px-4 text-right text-[var(--admin-muted)]">
                                                {(log.response_time_ms / 1000).toFixed(2)}s
                                            </td>
                                            <td className="py-3 px-4 text-right font-mono text-xs text-indigo-600">
                                                ${costVal.toFixed(5)} <span className="text-[10px] text-[var(--admin-muted)]">({(costVal * 600).toFixed(2)} F)</span>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}
