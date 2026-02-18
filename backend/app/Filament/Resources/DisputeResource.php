<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DisputeResource\Pages;
use App\Filament\Resources\DisputeResource\RelationManagers;
use App\Models\Dispute;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DisputeResource extends Resource
{
    protected static ?string $model = Dispute::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Litiges';

    protected static ?string $navigationGroup = 'Litiges & Support';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'litige';

    protected static ?string $pluralModelLabel = 'litiges';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Projet et Parties')
                    ->schema([
                        Forms\Components\Select::make('project_id')
                            ->label('Projet')
                            ->relationship('project', 'title')
                            ->searchable()
                            ->required()
                            ->disabled(fn ($context) => $context === 'edit'),
                        Forms\Components\Select::make('initiator_id')
                            ->label('Initié par')
                            ->relationship('initiator', 'name')
                            ->searchable()
                            ->required()
                            ->disabled(fn ($context) => $context === 'edit'),
                        Forms\Components\Select::make('respondent_id')
                            ->label('Contre')
                            ->relationship('respondent', 'name')
                            ->searchable()
                            ->required()
                            ->disabled(fn ($context) => $context === 'edit'),
                    ])->columns(3),

                Forms\Components\Section::make('Détails du litige')
                    ->schema([
                        Forms\Components\Select::make('dispute_type')
                            ->label('Type de litige')
                            ->required()
                            ->options([
                                'quality' => 'Qualité du travail',
                                'payment' => 'Paiement',
                                'delay' => 'Retard',
                                'fraud' => 'Fraude',
                                'other' => 'Autre',
                            ]),
                        Forms\Components\Select::make('priority')
                            ->label('Priorité')
                            ->required()
                            ->default('medium')
                            ->options([
                                'low' => 'Basse',
                                'medium' => 'Moyenne',
                                'high' => 'Haute',
                                'urgent' => 'Urgente',
                            ]),
                        Forms\Components\TextInput::make('reason')
                            ->label('Raison')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->required()
                            ->rows(5)
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('evidence')
                            ->label('Preuves')
                            ->multiple()
                            ->directory('disputes/evidence')
                            ->image()
                            ->maxFiles(5)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Statut')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Statut')
                            ->required()
                            ->default('open')
                            ->options([
                                'open' => 'Ouvert',
                                'investigating' => 'En investigation',
                                'resolved' => 'Résolu',
                                'cancelled' => 'Annulé',
                            ])
                            ->disabled(fn ($context) => $context === 'edit'),
                    ]),

                Forms\Components\Section::make('Résolution')
                    ->schema([
                        Forms\Components\Textarea::make('resolution')
                            ->label('Résolution')
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\Select::make('resolution_type')
                            ->label('Type de résolution')
                            ->options([
                                'refund' => 'Remboursement',
                                'partial_refund' => 'Remboursement partiel',
                                'release_funds' => 'Libération des fonds',
                                'project_cancelled' => 'Projet annulé',
                                'other' => 'Autre',
                            ]),
                        Forms\Components\Select::make('resolved_by')
                            ->label('Résolu par')
                            ->relationship('resolver', 'name')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('resolved_at')
                            ->label('Résolu le')
                            ->disabled(),
                    ])
                    ->columns(2)
                    ->visible(fn ($get) => $get('status') === 'resolved'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('project.title')
                    ->label('Projet')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('initiator.name')
                    ->label('Initié par')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('respondent.name')
                    ->label('Contre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('dispute_type')
                    ->label('Type')
                    ->colors([
                        'danger' => 'fraud',
                        'warning' => fn ($state) => in_array($state, ['quality', 'delay']),
                        'info' => fn ($state) => in_array($state, ['payment', 'other']),
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'quality' => 'Qualité',
                        'payment' => 'Paiement',
                        'delay' => 'Retard',
                        'fraud' => 'Fraude',
                        'other' => 'Autre',
                        default => $state,
                    }),
                Tables\Columns\BadgeColumn::make('priority')
                    ->label('Priorité')
                    ->colors([
                        'success' => 'low',
                        'warning' => 'medium',
                        'danger' => fn ($state) => in_array($state, ['high', 'urgent']),
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'low' => 'Basse',
                        'medium' => 'Moyenne',
                        'high' => 'Haute',
                        'urgent' => 'Urgente',
                        default => $state,
                    }),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Statut')
                    ->colors([
                        'warning' => 'open',
                        'info' => 'investigating',
                        'success' => 'resolved',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'open' => 'Ouvert',
                        'investigating' => 'En investigation',
                        'resolved' => 'Résolu',
                        'cancelled' => 'Annulé',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('messages_count')
                    ->label('Messages')
                    ->counts('messages')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->multiple()
                    ->options([
                        'open' => 'Ouvert',
                        'investigating' => 'En investigation',
                        'resolved' => 'Résolu',
                        'cancelled' => 'Annulé',
                    ]),
                Tables\Filters\SelectFilter::make('priority')
                    ->label('Priorité')
                    ->multiple()
                    ->options([
                        'low' => 'Basse',
                        'medium' => 'Moyenne',
                        'high' => 'Haute',
                        'urgent' => 'Urgente',
                    ]),
                Tables\Filters\SelectFilter::make('dispute_type')
                    ->label('Type')
                    ->multiple()
                    ->options([
                        'quality' => 'Qualité',
                        'payment' => 'Paiement',
                        'delay' => 'Retard',
                        'fraud' => 'Fraude',
                        'other' => 'Autre',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Créé du'),
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
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('investigate')
                    ->label('Investiguer')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('info')
                    ->visible(fn (Dispute $record): bool => $record->status === 'open')
                    ->requiresConfirmation()
                    ->modalHeading('Démarrer l\'investigation')
                    ->modalDescription('Voulez-vous démarrer l\'investigation de ce litige ?')
                    ->action(function (Dispute $record): void {
                        $record->update([
                            'status' => 'investigating',
                        ]);
                    }),
                Tables\Actions\Action::make('resolve')
                    ->label('Résoudre')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Dispute $record): bool => !in_array($record->status, ['resolved', 'cancelled']))
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('resolution')
                            ->label('Résolution')
                            ->required()
                            ->rows(4)
                            ->placeholder('Décrivez la résolution du litige'),
                        Forms\Components\Select::make('resolution_type')
                            ->label('Type de résolution')
                            ->required()
                            ->options([
                                'refund' => 'Remboursement',
                                'partial_refund' => 'Remboursement partiel',
                                'release_funds' => 'Libération des fonds',
                                'project_cancelled' => 'Projet annulé',
                                'other' => 'Autre',
                            ]),
                    ])
                    ->action(function (Dispute $record, array $data): void {
                        $record->update([
                            'status' => 'resolved',
                            'resolution' => $data['resolution'],
                            'resolution_type' => $data['resolution_type'],
                            'resolved_by' => auth()->id(),
                            'resolved_at' => now(),
                        ]);

                        // TODO: Send notifications to both parties
                        // TODO: Trigger financial actions based on resolution_type
                    }),
                Tables\Actions\Action::make('cancel')
                    ->label('Annuler')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Dispute $record): bool => !in_array($record->status, ['resolved', 'cancelled']))
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label('Raison de l\'annulation')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Dispute $record, array $data): void {
                        $record->update([
                            'status' => 'cancelled',
                            'resolution' => 'Litige annulé: ' . $data['cancellation_reason'],
                        ]);
                    }),
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
            'index' => Pages\ListDisputes::route('/'),
            'create' => Pages\CreateDispute::route('/create'),
            'edit' => Pages\EditDispute::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
