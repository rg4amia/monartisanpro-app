<?php

namespace App\Filament\Widgets;

use App\Models\EscrowWallet;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EscrowStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalEscrowValue = EscrowWallet::where('status', 'active')->sum('total_amount');
        $activeWalletsCount = EscrowWallet::where('status', 'active')->count();
        $totalMaterialBalance = EscrowWallet::where('status', 'active')
            ->get()
            ->sum(fn ($wallet) => $wallet->material_wallet - $wallet->material_spent);

        return [
            Stat::make('Valeur totale séquestre', number_format($totalEscrowValue, 0, ',', ' ') . ' XOF')
                ->description('Montant total en séquestre actif')
                ->color('success')
                ->icon('heroicon-o-banknotes'),
            Stat::make('Portefeuilles actifs', $activeWalletsCount)
                ->description('Projets avec séquestre actif')
                ->color('info')
                ->icon('heroicon-o-wallet'),
            Stat::make('Solde matériel total', number_format($totalMaterialBalance, 0, ',', ' ') . ' XOF')
                ->description('Disponible pour achats matériel')
                ->color('warning')
                ->icon('heroicon-o-cube'),
        ];
    }
}
