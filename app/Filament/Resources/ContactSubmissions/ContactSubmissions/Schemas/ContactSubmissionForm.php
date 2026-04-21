<?php

namespace App\Filament\Resources\ContactSubmissions\ContactSubmissions\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContactSubmissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del contacto')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(50),
                        TextInput::make('rut')
                            ->label('RUT')
                            ->maxLength(20),
                    ]),

                Section::make('Campos del formulario')
                    ->description('Edita los valores enviados desde el formulario de contacto.')
                    ->components([
                        KeyValue::make('fields')
                            ->label('Campos dinámicos')
                            ->keyLabel('Campo')
                            ->valueLabel('Valor')
                            ->addActionLabel('Agregar campo')
                            ->columnSpanFull(),
                    ]),

                Section::make('Salesforce')
                    ->columns(2)
                    ->components([
                        TextInput::make('salesforce_case_id')
                            ->label('ID Lead Salesforce')
                            ->placeholder('Sin sincronizar')
                            ->readOnly(),
                        TextInput::make('salesforce_case_error')
                            ->label('Error previo')
                            ->placeholder('Sin errores')
                            ->readOnly()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
