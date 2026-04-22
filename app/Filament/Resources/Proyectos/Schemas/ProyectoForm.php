<?php

namespace App\Filament\Resources\Proyectos\Schemas;

use App\Models\Proyecto;
use Awcodes\Curator\Components\Forms\CuratorPicker;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProyectoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Información General')
                    ->description('Datos básicos del proyecto')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre del Proyecto')
                            ->disabled()
                            ->required(),

                        TextInput::make('salesforce_id')
                            ->label('Salesforce Product ID')
                            ->disabled()
                            ->suffixAction(
                                Action::make('openSalesforceProject')
                                    ->label('Ver en Salesforce')
                                    ->icon('heroicon-m-arrow-top-right-on-square')
                                    ->url(fn (?Proyecto $record): ?string => filled($record?->salesforce_id)
                                        ? "https://leben.lightning.force.com/lightning/r/Proyecto__c/{$record->salesforce_id}/view"
                                        : null)
                                    ->openUrlInNewTab()
                                    ->visible(fn (?Proyecto $record): bool => filled($record?->salesforce_id))
                            ),

                        Textarea::make('descripcion')
                            ->label('Descripción')
                            ->disabled()
                            ->rows(3),

                        Select::make('tipo')
                            ->label('Tipo de Proyecto')
                            ->multiple()
                            ->options([
                                'best' => 'Best',
                                'broker' => 'Broker',
                                'home' => 'Home',
                                'icon' => 'Icon',
                                'invest' => 'Invest',
                            ])
                            ->searchable()
                            ->preload(),

                        TextInput::make('direccion')
                            ->label('Dirección')
                            ->disabled(),

                        TextInput::make('comuna')
                            ->label('Comuna')
                            ->disabled(),

                        TextInput::make('provincia')
                            ->label('Provincia')
                            ->disabled(),

                        TextInput::make('region')
                            ->label('Región')
                            ->disabled(),

                        TextInput::make('razon_social')
                            ->label('Razón Social')
                            ->disabled(),

                        TextInput::make('rut')
                            ->label('RUT')
                            ->disabled(),

                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->disabled(),

                        TextInput::make('telefono')
                            ->label('Teléfono')
                            ->disabled(),

                        TextInput::make('pagina_web')
                            ->label('Página Web')
                            ->url()
                            ->disabled(),

                        DatePicker::make('fecha_inicio_ventas')
                            ->label('Fecha Inicio Ventas')
                            ->disabled(),

                        TextInput::make('fecha_entrega')
                            ->label('Fecha de Entrega')
                            ->disabled(),

                        TextInput::make('etapa')
                            ->label('Etapa')
                            ->disabled(),

                        TextInput::make('horario_atencion')
                            ->label('Horario de Atención')
                            ->disabled(),

                        Toggle::make('is_active')
                            ->label('Activo')
                            ->required(),

                        Select::make('asesores')
                            ->label('Asesores')
                            ->relationship(
                                name: 'asesores',
                                titleAttribute: 'email',
                                modifyQueryUsing: fn (Builder $query): Builder => $query
                                    ->orderBy('first_name')
                                    ->orderBy('last_name')
                            )
                            ->multiple()
                            ->preload()
                            ->searchable(['first_name', 'last_name', 'email'])
                            ->getOptionLabelFromRecordUsing(function (Model $record): string {
                                $fullName = trim(implode(' ', array_filter([$record->first_name, $record->last_name])));

                                if ($fullName !== '' && filled($record->email)) {
                                    return "{$fullName} ({$record->email})";
                                }

                                return $fullName !== '' ? $fullName : (string) ($record->email ?? 'Asesor');
                            })
                            ->helperText('Puedes vincular uno o más asesores a este proyecto. Los asesores Salesforce se sincronizan automáticamente al sincronizar proyectos.')
                            ->columnSpan(2),

                        CuratorPicker::make('project_image_id')
                            ->label('Imagen del Proyecto')
                            ->helperText('Imagen manual del proyecto. Si no se define, se usará la Portada de Salesforce; si tampoco existe, se usará el logo principal del sitio o un ícono por defecto.')
                            ->columnSpan(2),

                        Section::make('Branding Salesforce')
                            ->description('Activos sincronizados automáticamente desde Salesforce para este proyecto')
                            ->schema([
                                TextInput::make('salesforce_portada_url')
                                    ->label('Portada (Salesforce)')
                                    ->disabled()
                                    ->url()
                                    ->suffixAction(
                                        Action::make('openSalesforcePortada')
                                            ->icon('heroicon-m-arrow-top-right-on-square')
                                            ->url(fn (?Proyecto $record): ?string => $record?->salesforce_portada_url)
                                            ->openUrlInNewTab()
                                            ->visible(fn (?Proyecto $record): bool => filled($record?->salesforce_portada_url))
                                    ),

                                TextInput::make('salesforce_logo_url')
                                    ->label('Logo (Salesforce)')
                                    ->disabled()
                                    ->url()
                                    ->suffixAction(
                                        Action::make('openSalesforceLogo')
                                            ->icon('heroicon-m-arrow-top-right-on-square')
                                            ->url(fn (?Proyecto $record): ?string => $record?->salesforce_logo_url)
                                            ->openUrlInNewTab()
                                            ->visible(fn (?Proyecto $record): bool => filled($record?->salesforce_logo_url))
                                    ),

                                Placeholder::make('salesforce_branding_hint')
                                    ->hiddenLabel()
                                    ->content('La portada se usa como fallback de "Imagen del Proyecto" cuando no hay imagen manual cargada en Curator.')
                                    ->columnSpan(2),
                            ])
                            ->columns(2)
                            ->columnSpan(2),
                    ])
                    ->columns(2),

                Section::make('Pago y Financiamiento')
                    ->description('Configuración de códigos de comercio, pago manual, reserva exigida y entrega inmediata')
                    ->schema([
                        Select::make('transbank_commerce_code')
                            ->label('Código de Comercio Transbank Mall')
                            ->helperText('Código único de comercio para Transbank en este proyecto')
                            ->options(function () {
                                $codes = config('payments.gateways.transbank.commerce_codes', []);

                                if (empty($codes)) {
                                    return [];
                                }

                                // Crear opciones: code => "Nombre del Proyecto - CODE"
                                $options = [];
                                foreach ($codes as $slug => $code) {
                                    // Buscar el proyecto por slug
                                    $proyecto = Proyecto::where('slug', $slug)->first();

                                    // Si encuentra proyecto, mostrar nombre + código
                                    // Si no, mostrar slug + código como fallback
                                    if ($proyecto) {
                                        $label = "{$proyecto->name}";
                                    } else {
                                        $label = "{$slug}";
                                    }

                                    $options[$code] = $label;
                                }

                                return $options;
                            })
                            ->searchable()
                            ->nullable(),

                        Section::make('Pago Manual por Proyecto')
                            ->description('Configura los datos de depósito y/o link de pago para este proyecto')
                            ->schema([
                                TextInput::make('manual_payment_link')
                                    ->label('Link de Pago del Proyecto')
                                    ->url()
                                    ->placeholder('https://...')
                                    ->helperText('Si se define, se enviará al checkout manual de este proyecto.'),

                                Textarea::make('manual_payment_instructions')
                                    ->label('Instrucciones de Depósito')
                                    ->rows(3)
                                    ->maxLength(2000)
                                    ->helperText('Estas instrucciones tienen prioridad sobre la configuración global.'),

                                Repeater::make('manual_payment_bank_accounts')
                                    ->label('Cuentas para Depósito')
                                    ->schema([
                                        TextInput::make('bank')
                                            ->label('Banco')
                                            ->maxLength(150),
                                        TextInput::make('account_type')
                                            ->label('Tipo de Cuenta')
                                            ->maxLength(100),
                                        TextInput::make('account_number')
                                            ->label('Número de Cuenta')
                                            ->maxLength(100),
                                        TextInput::make('account_holder')
                                            ->label('Titular')
                                            ->maxLength(150),
                                        TextInput::make('rut')
                                            ->label('RUT Titular')
                                            ->maxLength(20),
                                    ])
                                    ->columns(2)
                                    ->defaultItems(0)
                                    ->reorderable(false)
                                    ->collapsible()
                                    ->helperText('Puedes agregar una o más cuentas de depósito para este proyecto.'),
                            ])
                            ->columns(1),

                        Section::make('Reserva Exigida')
                            ->schema([
                                TextInput::make('valor_reserva_exigido_defecto_peso')
                                    ->label('Valor Defecto ($)')
                                    ->numeric()
                                    ->prefix('$'),

                                TextInput::make('valor_reserva_exigido_min_peso')
                                    ->label('Valor Mínimo ($)')
                                    ->numeric()
                                    ->disabled()
                                    ->prefix('$'),
                            ])
                            ->columns(2),

                        Toggle::make('entrega_inmediata')
                            ->label('Entrega Inmediata')
                            ->disabled(),
                    ]),
            ]);
    }
}
