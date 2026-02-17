<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectResource\Pages;
use App\Filament\Resources\ProjectResource\RelationManagers;
use App\Models\Project;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationLabel = 'Projets';

    protected static ?string $modelLabel = 'projet';

    protected static ?string $pluralModelLabel = 'projets';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations du projet')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Titre')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('address')
                            ->label('Adresse')
                            ->maxLength(255)
                            ->columnSpan(2),
                    ])->columns(2),

                Forms\Components\Section::make('Intervenants')
                    ->schema([
                        Forms\Components\Select::make('client_id')
                            ->label('Client')
                            ->relationship('client', 'name')
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('artisan_id')
                            ->label('Artisan')
                            ->relationship('artisan', 'name')
                            ->searchable()
                            ->nullable(),
                        Forms\Components\Select::make('trade_id')
                            ->label('Métier')
                            ->relationship('trade', 'name')
                            ->searchable()
                            ->required(),
                    ])->columns(3),

                Forms\Components\Section::make('Budget')
                    ->schema([
                        Forms\Components\TextInput::make('budget_min')
                            ->label('Budget minimum (XOF)')
                            ->numeric()
                            ->prefix('XOF'),
                        Forms\Components\TextInput::make('budget_max')
                            ->label('Budget maximum (XOF)')
                            ->numeric()
                            ->prefix('XOF'),
                        Forms\Components\TextInput::make('final_amount')
                            ->label('Montant final (XOF)')
                            ->numeric()
                            ->prefix('XOF')
                            ->disabled(),
                    ])->columns(3),

                Forms\Components\Section::make('Statut et dates')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Statut')
                            ->options([
                                'pending' => 'En attente',
                                'awaiting_quotes' => 'En attente de devis',
                                'quote_received' => 'Devis reçu',
                                'quote_accepted' => 'Devis accepté',
                                'payment_pending' => 'Paiement en attente',
                                'in_progress' => 'En cours',
                                'completed' => 'Terminé',
                                'cancelled' => 'Annulé',
                                'disputed' => 'En litige',
                            ])
                            ->required(),
                        Forms\Components\DateTimePicker::make('started_at')
                            ->label('Date de début'),
                        Forms\Components\DateTimePicker::make('completed_at')
                            ->label('Date de fin'),
                        Forms\Components\DateTimePicker::make('cancelled_at')
                            ->label('Date d\'annulation'),
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label('Raison de l\'annulation')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Titre')
                    ->searchable()
                    ->limit(30)
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('artisan.name')
                    ->label('Artisan')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Non assigné'),
                Tables\Columns\TextColumn::make('trade.name')
                    ->label('Métier')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Statut')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'awaiting_quotes',
                        'primary' => 'quote_received',
                        'success' => 'quote_accepted',
                        'warning' => 'payment_pending',
                        'primary' => 'in_progress',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                        'danger' => 'disputed',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'En attente',
                        'awaiting_quotes' => 'Attente devis',
                        'quote_received' => 'Devis reçu',
                        'quote_accepted' => 'Devis accepté',
                        'payment_pending' => 'Paiement en attente',
                        'in_progress' => 'En cours',
                        'completed' => 'Terminé',
                        'cancelled' => 'Annulé',
                        'disputed' => 'En litige',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('budget_range')
                    ->label('Budget')
                    ->getStateUsing(function (Project $record): string {
                        if ($record->budget_min && $record->budget_max) {
                            return number_format($record->budget_min, 0, ',', ' ') . ' - ' . number_format($record->budget_max, 0, ',', ' ') . ' XOF';
                        }
                        return 'Non défini';
                    }),
                Tables\Columns\TextColumn::make('quote_count')
                    ->label('Nb devis')
                    ->sortable()
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'pending' => 'En attente',
                        'awaiting_quotes' => 'Attente devis',
                        'quote_received' => 'Devis reçu',
                        'quote_accepted' => 'Devis accepté',
                        'payment_pending' => 'Paiement en attente',
                        'in_progress' => 'En cours',
                        'completed' => 'Terminé',
                        'cancelled' => 'Annulé',
                        'disputed' => 'En litige',
                    ])
                    ->multiple(),
                Tables\Filters\SelectFilter::make('trade_id')
                    ->label('Métier')
                    ->relationship('trade', 'name')
                    ->searchable(),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Créé à partir du'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Créé jusqu\'au'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('cancel')
                    ->label('Annuler')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Project $record): bool => !in_array($record->status, ['completed', 'cancelled']))
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label('Raison de l\'annulation')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Project $record, array $data): void {
                        $record->update([
                            'status' => 'cancelled',
                            'cancelled_at' => now(),
                            'cancellation_reason' => $data['cancellation_reason'],
                        ]);
                    }),
                Tables\Actions\Action::make('assign_artisan')
                    ->label('Assigner artisan')
                    ->icon('heroicon-o-user-plus')
                    ->color('primary')
                    ->visible(fn (Project $record): bool => $record->artisan_id === null)
                    ->form([
                        Forms\Components\Select::make('artisan_id')
                            ->label('Artisan')
                            ->relationship('artisan', 'name')
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Project $record, array $data): void {
                        $record->update(['artisan_id' => $data['artisan_id']]);
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    // Export functionality can be added later with filament-excel package
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\QuotesRelationManager::class,
            RelationManagers\MilestonesRelationManager::class,
            RelationManagers\TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
        ];
    }
}
