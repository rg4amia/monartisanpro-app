<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TradeResource\Pages;
use App\Filament\Resources\TradeResource\RelationManagers;
use App\Models\Trade;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TradeResource extends Resource
{
    protected static ?string $model = Trade::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Métiers';

    protected static ?string $navigationGroup = 'Catalogue & Zones';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'métier';

    protected static ?string $pluralModelLabel = 'métiers';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('sector_id')
                    ->label('Secteur')
                    ->relationship('sector', 'name')
                    ->searchable()
                    ->required()
                    ->preload(),
                Forms\Components\TextInput::make('code')
                    ->label('Code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(10),
                Forms\Components\TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(100),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sector.name')
                    ->label('Secteur')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('artisan_profiles_count')
                    ->label('Artisans')
                    ->counts('artisanProfiles')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 50 => 'success',
                        $state >= 20 => 'warning',
                        $state >= 5 => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('avg_quote_amount')
                    ->label('Devis moyen')
                    ->getStateUsing(function (Trade $record): ?string {
                        $avgAmount = $record->artisanProfiles()
                            ->join('projects', 'artisan_profiles.user_id', '=', 'projects.artisan_id')
                            ->join('quotes', 'projects.id', '=', 'quotes.project_id')
                            ->where('quotes.status', 'accepted')
                            ->avg('quotes.total_amount');

                        return $avgAmount ? number_format($avgAmount, 0, ',', ' ') . ' XOF' : '-';
                    })
                    ->sortable(false),
                Tables\Columns\TextColumn::make('project_count')
                    ->label('Projets')
                    ->getStateUsing(function (Trade $record): int {
                        return $record->artisanProfiles()
                            ->join('projects', 'artisan_profiles.user_id', '=', 'projects.artisan_id')
                            ->count();
                    })
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('sector_id')
                    ->label('Secteur')
                    ->relationship('sector', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrades::route('/'),
            'create' => Pages\CreateTrade::route('/create'),
            'edit' => Pages\EditTrade::route('/{record}/edit'),
        ];
    }
}
