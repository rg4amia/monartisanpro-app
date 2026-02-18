<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManagers;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = 'Transactions';

    protected static ?string $navigationGroup = 'Finance & Paiements';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'transaction';

    protected static ?string $pluralModelLabel = 'transactions';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('project_id')
                    ->label('Projet')
                    ->relationship('project', 'title')
                    ->searchable()
                    ->disabled(),
                Forms\Components\Select::make('user_id')
                    ->label('Utilisateur')
                    ->relationship('user', 'name')
                    ->required()
                    ->disabled(),
                Forms\Components\TextInput::make('transaction_ref')
                    ->label('Référence')
                    ->required()
                    ->disabled()
                    ->maxLength(255),
                Forms\Components\Select::make('type')
                    ->label('Type')
                    ->required()
                    ->disabled()
                    ->options([
                        'deposit' => 'Dépôt',
                        'token_redemption' => 'Rachat jeton',
                        'labor_release' => 'Libération main-d\'œuvre',
                        'refund' => 'Remboursement',
                        'commission' => 'Commission',
                    ]),
                Forms\Components\TextInput::make('amount')
                    ->label('Montant (XOF)')
                    ->required()
                    ->numeric()
                    ->prefix('XOF')
                    ->disabled(),
                Forms\Components\TextInput::make('payment_method')
                    ->label('Méthode de paiement')
                    ->disabled()
                    ->maxLength(255),
                Forms\Components\Select::make('status')
                    ->label('Statut')
                    ->required()
                    ->options([
                        'pending' => 'En attente',
                        'completed' => 'Complété',
                        'failed' => 'Échoué',
                        'cancelled' => 'Annulé',
                    ]),
                Forms\Components\Textarea::make('metadata')
                    ->label('Métadonnées')
                    ->disabled()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('transaction_ref')
                    ->label('Référence')
                    ->searchable()
                    ->copyable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('project.title')
                    ->label('Projet')
                    ->searchable()
                    ->sortable()
                    ->limit(25)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Utilisateur')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'success' => 'deposit',
                        'warning' => fn ($state) => in_array($state, ['token_redemption', 'labor_release']),
                        'danger' => 'refund',
                        'info' => 'commission',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'deposit' => 'Dépôt',
                        'token_redemption' => 'Rachat jeton',
                        'labor_release' => 'Libération MO',
                        'refund' => 'Remboursement',
                        'commission' => 'Commission',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Montant')
                    ->money('XOF')
                    ->sortable()
                    ->color(fn (float $state): string => $state < 0 ? 'danger' : 'success'),
                Tables\Columns\BadgeColumn::make('payment_method')
                    ->label('Méthode')
                    ->searchable()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'wave' => 'Wave',
                        'orange_money' => 'Orange Money',
                        'mtn' => 'MTN Mobile Money',
                        'moov' => 'Moov Money',
                        'manual' => 'Manuel',
                        default => $state ?? '-',
                    }),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Statut')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'completed',
                        'danger' => fn ($state) => in_array($state, ['failed', 'cancelled']),
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'En attente',
                        'completed' => 'Complété',
                        'failed' => 'Échoué',
                        'cancelled' => 'Annulé',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Type')
                    ->multiple()
                    ->options([
                        'deposit' => 'Dépôt',
                        'token_redemption' => 'Rachat jeton',
                        'labor_release' => 'Libération main-d\'œuvre',
                        'refund' => 'Remboursement',
                        'commission' => 'Commission',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->multiple()
                    ->options([
                        'pending' => 'En attente',
                        'completed' => 'Complété',
                        'failed' => 'Échoué',
                        'cancelled' => 'Annulé',
                    ]),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Méthode de paiement')
                    ->options([
                        'wave' => 'Wave',
                        'orange_money' => 'Orange Money',
                        'mtn' => 'MTN Mobile Money',
                        'moov' => 'Moov Money',
                        'manual' => 'Manuel',
                    ]),
                Tables\Filters\Filter::make('amount')
                    ->form([
                        Forms\Components\TextInput::make('amount_from')
                            ->label('Montant minimum')
                            ->numeric(),
                        Forms\Components\TextInput::make('amount_to')
                            ->label('Montant maximum')
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['amount_from'],
                                fn (Builder $query, $amount): Builder => $query->where('amount', '>=', $amount),
                            )
                            ->when(
                                $data['amount_to'],
                                fn (Builder $query, $amount): Builder => $query->where('amount', '<=', $amount),
                            );
                    }),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Du'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Au'),
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
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // No bulk actions for transactions (read-only)
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
            'index' => Pages\ListTransactions::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Transactions are system-generated only
    }
}
