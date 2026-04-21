<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información Personal')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre Completo')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Correo Electrónico')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(20)
                            ->placeholder('+56 9 1234 5678'),

                        TextInput::make('rut')
                            ->label('RUT')
                            ->unique(ignoreRecord: true)
                            ->maxLength(12)
                            ->placeholder('12.345.678-9')
                            ->helperText('Formato: 12.345.678-9'),
                    ])
                    ->columns(2),

                Section::make('Cuenta y Acceso')
                    ->schema([
                        Select::make('user_type')
                            ->label('Tipo de Usuario')
                            ->options([
                                'customer' => 'Cliente',
                                'marketing' => 'Marketing',
                                'admin' => 'Administrador',
                            ])
                            ->default('customer')
                            ->required()
                            ->helperText('Solo Administrador y Marketing pueden acceder al panel'),

                        TextInput::make('password')
                            ->label('Contraseña')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->helperText(fn (string $context): string => $context === 'edit' ? 'Dejar en blanco para mantener la contraseña actual' : '')
                            ->maxLength(255),

                        DateTimePicker::make('email_verified_at')
                            ->label('Email Verificado')
                            ->helperText('Fecha en que el usuario verificó su email'),
                    ])
                    ->columns(2),
            ]);
    }
}
