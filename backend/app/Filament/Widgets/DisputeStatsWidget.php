<?php

namespace App\Filament\Widgets;

use App\Models\Dispute;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DisputeStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $openCount = Dispute::where('status', 'open')->count();
        $investigatingCount = Dispute::where('status', 'investigating')->count();
        $urgentCount = Dispute::where('priority', 'urgent')->count();

        return [
            Stat::make('Litiges ouverts', $openCount)
                ->description('Nouveaux litiges à traiter')
                ->color('warning')
                ->icon('heroicon-o-exclamation-circle'),
            Stat::make('En investigation', $investigatingCount)
                ->description('Litiges en cours d\'investigation')
                ->color('info')
                ->icon('heroicon-o-magnifying-glass'),
            Stat::make('Litiges urgents', $urgentCount)
                ->description('Nécessitent une attention immédiate')
                ->color('danger')
                ->icon('heroicon-o-bell-alert'),
        ];
    }
}
