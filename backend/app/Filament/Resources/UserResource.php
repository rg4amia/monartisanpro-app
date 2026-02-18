<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Utilisateurs';

    protected static ?string $navigationGroup = 'Utilisateurs & KYC';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'utilisateur';

    protected static ?string $pluralModelLabel = 'utilisateurs';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations personnelles')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nom')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->label('Téléphone')
                            ->tel()
                            ->maxLength(20),
                        Forms\Components\FileUpload::make('avatar')
                            ->label('Avatar')
                            ->image()
                            ->directory('avatars')
                            ->nullable(),
                    ])->columns(2),

                Forms\Components\Section::make('Rôle et permissions')
                    ->schema([
                        Forms\Components\Select::make('role')
                            ->label('Rôle')
                            ->required()
                            ->options([
                                'client' => 'Client',
                                'artisan' => 'Artisan',
                                'fournisseur' => 'Fournisseur',
                                'referent' => 'Référent',
                                'admin' => 'Administrateur',
                            ]),
                        Forms\Components\Select::make('status')
                            ->label('Statut')
                            ->required()
                            ->default('active')
                            ->options([
                                'active' => 'Actif',
                                'suspended' => 'Suspendu',
                                'banned' => 'Banni',
                            ]),
                    ])->columns(2),

                Forms\Components\Section::make('Vérifications')
                    ->schema([
                        Forms\Components\Select::make('kyc_status')
                            ->label('Statut KYC')
                            ->required()
                            ->default('pending')
                            ->options([
                                'pending' => 'En attente',
                                'approved' => 'Approuvé',
                                'rejected' => 'Rejeté',
                            ])
                            ->disabled(fn ($context) => $context === 'edit'),
                        Forms\Components\DateTimePicker::make('phone_verified_at')
                            ->label('Téléphone vérifié le')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('email_verified_at')
                            ->label('Email vérifié le')
                            ->disabled(),
                    ])->columns(3),

                Forms\Components\Section::make('Sécurité')
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->label('Mot de passe')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn ($context) => $context === 'create')
                            ->maxLength(255)
                            ->visible(fn ($context) => $context === 'create'),
                        Forms\Components\Textarea::make('suspended_reason')
                            ->label('Raison de la suspension')
                            ->rows(3)
                            ->columnSpanFull()
                            ->visible(fn ($get) => $get('status') === 'suspended'),
                        Forms\Components\Textarea::make('ban_reason')
                            ->label('Raison du bannissement')
                            ->rows(3)
                            ->columnSpanFull()
                            ->visible(fn ($get) => $get('status') === 'banned'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\BadgeColumn::make('role')
                    ->label('Rôle')
                    ->colors([
                        'primary' => 'client',
                        'warning' => 'artisan',
                        'info' => 'fournisseur',
                        'success' => 'referent',
                        'danger' => 'admin',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'client' => 'Client',
                        'artisan' => 'Artisan',
                        'fournisseur' => 'Fournisseur',
                        'referent' => 'Référent',
                        'admin' => 'Admin',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Téléphone')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\BadgeColumn::make('kyc_status')
                    ->label('KYC')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'En attente',
                        'approved' => 'Approuvé',
                        'rejected' => 'Rejeté',
                        default => $state,
                    }),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Statut')
                    ->colors([
                        'success' => 'active',
                        'danger' => fn ($state) => in_array($state, ['suspended', 'banned']),
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Actif',
                        'suspended' => 'Suspendu',
                        'banned' => 'Banni',
                        default => $state,
                    }),
                Tables\Columns\IconColumn::make('phone_verified_at')
                    ->label('Tél. vérifié')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->getStateUsing(fn ($record) => !is_null($record->phone_verified_at)),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Rôle')
                    ->multiple()
                    ->options([
                        'client' => 'Client',
                        'artisan' => 'Artisan',
                        'fournisseur' => 'Fournisseur',
                        'referent' => 'Référent',
                        'admin' => 'Admin',
                    ]),
                Tables\Filters\SelectFilter::make('kyc_status')
                    ->label('Statut KYC')
                    ->multiple()
                    ->options([
                        'pending' => 'En attente',
                        'approved' => 'Approuvé',
                        'rejected' => 'Rejeté',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->multiple()
                    ->options([
                        'active' => 'Actif',
                        'suspended' => 'Suspendu',
                        'banned' => 'Banni',
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
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('suspend')
                    ->label('Suspendre')
                    ->icon('heroicon-o-no-symbol')
                    ->color('warning')
                    ->visible(fn (User $record): bool => $record->status !== 'suspended')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('suspended_reason')
                            ->label('Raison de la suspension')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->update([
                            'status' => 'suspended',
                            'suspended_at' => now(),
                            'suspended_reason' => $data['suspended_reason'],
                        ]);
                    }),
                Tables\Actions\Action::make('reactivate')
                    ->label('Réactiver')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (User $record): bool => $record->status === 'suspended')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $record->update([
                            'status' => 'active',
                            'suspended_at' => null,
                            'suspended_reason' => null,
                        ]);
                    }),
                Tables\Actions\Action::make('ban')
                    ->label('Bannir')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->visible(fn (User $record): bool => $record->status !== 'banned')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('ban_reason')
                            ->label('Raison du bannissement')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->update([
                            'status' => 'banned',
                            'banned_at' => now(),
                            'ban_reason' => $data['ban_reason'],
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
