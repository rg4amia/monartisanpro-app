<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TransactionStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalVolume = Transaction::where('status', 'completed')->sum('amount');
        $totalTransactions = Transaction::count();
        $successRate = $totalTransactions > 0
            ? round((Transaction::where('status', 'completed')->count() / $totalTransactions) * 100, 1)
            : 0;
        $failedCount = Transaction::where('status', 'failed')->count();

        return [
            Stat::make('Volume total', number_format($totalVolume, 0, ',', ' ') . ' XOF')
                ->description('Montant total des transactions complétées')
                ->color('success')
                ->icon('heroicon-o-chart-bar'),
            Stat::make('Taux de succès', $successRate . '%')
                ->description($totalTransactions . ' transactions au total')
                ->color($successRate >= 95 ? 'success' : ($successRate >= 85 ? 'warning' : 'danger'))
                ->icon('heroicon-o-check-badge'),
            Stat::make('Transactions échouées', $failedCount)
                ->description('Nécessitent une attention')
                ->color($failedCount > 10 ? 'danger' : 'warning')
                ->icon('heroicon-o-exclamation-triangle'),
        ];
    }
}
