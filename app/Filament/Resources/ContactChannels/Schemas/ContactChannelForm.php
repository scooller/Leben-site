<?php

namespace App\Filament\Resources\ContactChannels\Schemas;

use App\Filament\Pages\SiteSettings;
use App\Models\SiteSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ContactChannelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identificación')
                ->columns(2)
                ->schema([
                    TextInput::make('slug')
                        ->label('Slug (identificador único)')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->alphaDash()
                        ->maxLength(100)
                        ->helperText('Minúsculas, guiones y números. Ej: sale, argomedo, capitanes'),
                    TextInput::make('name')
                        ->label('Nombre visible')
                        ->required()
                        ->maxLength(255),
                    Select::make('slug_badge_color')
                        ->label('Color del tag (slug)')
                        ->required()
                        ->default('gray')
                        ->options([
                            'gray' => 'Gris',
                            'primary' => 'Primario',
                            'info' => 'Info',
                            'success' => 'Verde',
                            'warning' => 'Amarillo',
                            'danger' => 'Rojo',
                        ]),
                ]),

            Section::make('Estado')
                ->columns(2)
                ->schema([
                    Toggle::make('is_active')
                        ->label('Activo')
                        ->default(true),
                    Toggle::make('is_default')
                        ->label('Canal por defecto')
                        ->helperText('Solo un canal puede ser el predeterminado. Los envíos sin canal asignado irán aquí.'),
                ]),

            Section::make('Notificaciones')
                ->schema([
                    TextInput::make('notification_email')
                        ->label('Email de notificación')
                        ->email()
                        ->nullable()
                        ->maxLength(255)
                        ->helperText('Si está vacío, se usará el email global configurado en Ajustes del sitio.'),
                ]),

            Section::make('Dominios asociados')
                ->description('Lista de dominios que se asocian automáticamente a este canal. Soporta comodines: *.sale.cl')
                ->schema([
                    Repeater::make('domain_patterns')
                        ->label('Patrones de dominio')
                        ->simple(
                            TextInput::make('value')
                                ->label('Dominio')
                                ->placeholder('sale.ileben.cl o *.sale.cl')
                                ->required()
                                ->maxLength(255),
                        )
                        ->addActionLabel('Agregar dominio')
                        ->reorderable()
                        ->collapsible()
                        ->defaultItems(0),
                ]),

            Section::make('Configuración de formulario')
                ->description('Si está vacío, se usará la configuración global del formulario de contacto (Ajustes del sitio). Define aquí los campos específicos de este canal.')
                ->headerActions([
                    Action::make('preloadGlobalForm')
                        ->label('Precargar formulario')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading('¿Precargar formulario global?')
                        ->modalDescription('Esto reemplazará los campos actuales con la configuración global del formulario de contacto (Ajustes del Sitio).')
                        ->modalSubmitActionLabel('Sí, precargar')
                        ->action(function (Set $set) {
                            $globalFields = SiteSetting::current()->contact_form_fields;

                            if (! is_array($globalFields) || empty($globalFields)) {
                                Notification::make()
                                    ->warning()
                                    ->title('Sin configuración global')
                                    ->body('No hay campos configurados globalmente en Ajustes del Sitio.')
                                    ->send();

                                return;
                            }

                            $keyedFields = [];
                            foreach ($globalFields as $field) {
                                $keyedFields[(string) \Illuminate\Support\Str::uuid()] = $field;
                            }

                            $set('form_fields', $keyedFields);

                            Notification::make()
                                ->success()
                                ->title('Formulario precargado')
                                ->body('Se cargó la configuración global del formulario de contacto.')
                                ->send();
                        }),
                ])
                ->schema([
                    Repeater::make('form_fields')
                        ->label('Campos del formulario')
                        ->schema(SiteSettings::getContactFormFieldsSchema())
                        ->defaultItems(0)
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => filled($state['label'] ?? null)
                            ? ($state['label'] . ' (' . ($state['key'] ?? '') . ')')
                            : null
                        )
                        ->columns(2)
                        ->columnSpanFull()
                        ->helperText('Define aquí los campos para este canal. Si está vacío, se usará la configuración global.'),
                ])
                ->collapsed(),
        ]);
    }
}
