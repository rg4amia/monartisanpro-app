// Graphiques SVG inline du backoffice, extraits de console.tsx (Chantier C2).

import type { ChartPoint, DualSeries } from './types';
import { EmptyState } from './ui';

export function DualLineChart({ series }: { series: DualSeries[] }) {
    const points = series[0]?.points ?? [];

    if (series.length === 0 || points.length === 0) {
        return <EmptyState description="Pas assez de données pour tracer ce graphique." title="Graphique indisponible" />;
    }

    const width = 720;
    const height = 290;
    const paddingX = 36;
    const paddingTop = 28;
    const paddingBottom = 46;
    const maxValue = Math.max(...series.flatMap((entry) => entry.points.map((point) => point.value)), 1);
    const xStep = points.length > 1 ? (width - paddingX * 2) / (points.length - 1) : 0;
    const graphHeight = height - paddingTop - paddingBottom;
    const toY = (value: number): number => paddingTop + graphHeight - (value / maxValue) * graphHeight;

    return (
        <div className="mt-5 rounded-[28px] border border-[var(--admin-border)] bg-white/60 p-4">
            <svg viewBox={`0 0 ${width} ${height}`} className="h-64 w-full overflow-visible">
                <defs>
                    {series.map((entry, seriesIndex) => (
                        <linearGradient key={entry.label} id={`chart-grad-${seriesIndex}`} x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor={entry.color} stopOpacity="0.22" />
                            <stop offset="100%" stopColor={entry.color} stopOpacity="0" />
                        </linearGradient>
                    ))}
                </defs>

                {Array.from({ length: 5 }, (_, index) => {
                    const ratio = index / 4;
                    const y = paddingTop + graphHeight * ratio;

                    return (
                        <line
                            key={index}
                            x1={paddingX}
                            y1={y}
                            x2={width - paddingX}
                            y2={y}
                            stroke="rgba(194, 170, 136, 0.35)"
                            strokeDasharray="4 7"
                        />
                    );
                })}

                {series.map((entry, seriesIndex) => {
                    if (entry.points.length < 2) return null;
                    const firstX = paddingX;
                    const lastX = paddingX + (entry.points.length - 1) * xStep;
                    const bottom = paddingTop + graphHeight;
                    const polygonPoints = [
                        ...entry.points.map((point, index) => `${paddingX + index * xStep},${toY(point.value)}`),
                        `${lastX},${bottom}`,
                        `${firstX},${bottom}`,
                    ].join(' ');

                    return (
                        <polygon
                            key={`fill-${entry.label}`}
                            points={polygonPoints}
                            fill={`url(#chart-grad-${seriesIndex})`}
                        />
                    );
                })}

                {series.map((entry) => {
                    const polyline = entry.points.map((point, index) => `${paddingX + index * xStep},${toY(point.value)}`).join(' ');

                    return <polyline key={entry.label} fill="none" points={polyline} stroke={entry.color} strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" />;
                })}

                {series.map((entry) =>
                    entry.points.map((point, index) => (
                        <circle
                            key={`${entry.label}-dot-${index}`}
                            cx={paddingX + index * xStep}
                            cy={toY(point.value)}
                            r="4"
                            fill="white"
                            stroke={entry.color}
                            strokeWidth="2.5"
                        />
                    )),
                )}

                {points.map((point, index) => {
                    const x = paddingX + index * xStep;

                    return (
                        <text key={point.label} x={x} y={height - 12} textAnchor="middle" fontSize="11" fill="rgba(110, 91, 66, 0.72)">
                            {point.label}
                        </text>
                    );
                })}
            </svg>

            <div className="mt-3 flex flex-wrap gap-5">
                {series.map((entry) => (
                    <div key={entry.label} className="flex items-center gap-2 text-sm text-[var(--admin-text-soft)]">
                        <span
                            className="h-3 w-3 rounded-full border-2 border-white shadow-sm"
                            ref={(el) => {
                                if (el) el.style.backgroundColor = entry.color;
                            }}
                        />
                        {entry.label}
                    </div>
                ))}
            </div>
        </div>
    );
}

export function VolumeBarChart({ bars, color }: { bars: ChartPoint[]; color: string }) {
    const maxValue = Math.max(...bars.map((bar) => bar.value), 1);

    return (
        <div className="mt-5 rounded-[28px] border border-[var(--admin-border)] bg-white/60 p-4">
            <div className="mb-2 flex items-center justify-end gap-1">
                <span className="text-[11px] text-[var(--admin-muted)]">max</span>
                <span className="text-[11px] font-semibold text-[var(--admin-text)]">{maxValue}</span>
            </div>
            <div className="flex h-52 items-end gap-1.5 overflow-hidden rounded-[20px] bg-[rgba(255,255,255,0.55)] p-3">
                {bars.map((bar) => {
                    const heightPercent = Math.max((bar.value / maxValue) * 100, bar.value > 0 ? 8 : 3);

                    return (
                        <div key={bar.label} className="group flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1.5">
                            <span className="text-[10px] font-medium text-[var(--admin-text)] opacity-0 transition-opacity group-hover:opacity-100">
                                {bar.value > 0 ? bar.value : ''}
                            </span>
                            <div
                                className="w-full rounded-t-[10px] transition-all duration-500 group-hover:opacity-100"
                                ref={(el) => {
                                    if (el) {
                                        el.style.backgroundColor = color;
                                        el.style.height = `${heightPercent}%`;
                                        el.style.opacity = '0.82';
                                    }
                                }}
                            />
                            <span className="text-[10px] text-[var(--admin-muted)]">{bar.label}</span>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
