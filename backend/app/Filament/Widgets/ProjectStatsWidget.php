<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProjectStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $total = Project::count();
        $pending = Project::where('status', 'pending')->count();
        $inProgress = Project::where('status', 'in_progress')->count();
        $completed = Project::where('status', 'completed')->count();
        $cancelled = Project::where('status', 'cancelled')->count();
        $disputed = Project::where('status', 'disputed')->count();

        return [
            Stat::make('Total Projets', $total)
                ->description('Tous les projets')
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('primary'),

            Stat::make('En Attente', $pending)
                ->description('Projets en attente')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('En Cours', $inProgress)
                ->description('Projets actifs')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('info'),

            Stat::make('Terminés', $completed)
                ->description('Projets complétés')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Annulés', $cancelled)
                ->description('Projets annulés')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),

            Stat::make('En Litige', $disputed)
                ->description('Projets disputés')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
