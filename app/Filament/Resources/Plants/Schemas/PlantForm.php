<?php

namespace App\Filament\Resources\Plants\Schemas;

use App\Models\Proyecto;
use Awcodes\Curator\Components\Forms\CuratorPicker;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PlantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('salesforce_product_id')
                    ->label('Salesforce Product ID')
                    ->required()
                    ->disabled()
                    ->suffixAction(
                        Action::make('openSalesforcePlant')
                            ->label('Ver en Salesforce')
                            ->icon('heroicon-o-arrow-top-right-on-square')
                            ->url(fn (?string $state): ?string => filled($state)
                                ? "https://leben.lightning.force.com/lightning/r/Product2/{$state}/view"
                                : null, shouldOpenInNewTab: true)
                    ),
                TextInput::make('name')
                    ->label('Nombre')
                    // ->disabled()
                    ->required(),
                TextInput::make('product_code')
                    ->label('Código de Producto')
                    // ->disabled()
                    ->required(),
                TextInput::make('tipo_producto')
                    ->label('Tipo de Planta')
                    ->disabled(),
                Select::make('salesforce_proyecto_id')
                    ->label('Proyecto')
                    ->options(Proyecto::pluck('name', 'salesforce_id'))
                    ->disabled()
                    ->searchable(),
                Select::make('asesor_id')
                    ->label('Asesor de planta')
                    ->relationship(
                        name: 'asesor',
                        titleAttribute: 'email',
                        modifyQueryUsing: fn (Builder $query): Builder => $query
                            ->where('is_active', true)
                            ->orderBy('first_name')
                            ->orderBy('last_name')
                    )
                    ->searchable(['first_name', 'last_name', 'email'])
                    ->getOptionLabelFromRecordUsing(function (Model $record): string {
                        $fullName = trim(implode(' ', array_filter([$record->first_name, $record->last_name])));

                        if ($fullName !== '' && filled($record->email)) {
                            return "{$fullName} ({$record->email})";
                        }

                        return $fullName !== '' ? $fullName : (string) ($record->email ?? 'Asesor');
                    })
                    ->nullable()
                    ->helperText('Si seleccionas un asesor de planta, tendrá prioridad sobre los asesores configurados en el proyecto.'),
                TextInput::make('contact_link')
                    ->label('Link de contacto')
                    ->maxLength(2048)
                    ->nullable()
                    ->helperText('Si está definido, el botón "Asesorate aquí" del detalle de planta usará este link (interno o externo).'),
                TextInput::make('piso')
                    ->label('Piso')
                    ->disabled(),
                TextInput::make('programa')
                    ->label('Programa')
                    ->disabled(),
                TextInput::make('programa2')
                    ->label('Programa 2')
                    ->disabled(),
                TextInput::make('orientacion')
                    ->label('Orientación')
                    ->disabled(),
                TextInput::make('precio_base')
                    ->label('Precio Base')
                    ->numeric()
                    ->prefix('$')
                    ->helperText('Intentar no modificar, se perdera al sincronizar.'),
                TextInput::make('precio_lista')
                    ->label('Precio Lista')
                    ->numeric()
                    ->prefix('$')
                    ->disabled(),
                TextInput::make('porcentaje_maximo_unidad')
                    ->label('Porcentaje Máximo de Unidad')
                    ->numeric()
                    ->suffix('%')
                    ->helperText('Solo para plantas con programa "Departamento". Intentar no modificar, se perdera al sincronizar.'),
                Toggle::make('unidad_sale')
                    ->label('Unidad Sale')
                    ->helperText('Define si esta unidad debe mostrarse cuando la configuración sale está activa.'),
                TextInput::make('superficie_total_principal')
                    ->label('Superficie Total Principal')
                    ->numeric()
                    ->suffix('m²')
                    ->disabled(),
                TextInput::make('superficie_interior')
                    ->label('Superficie Interior')
                    ->numeric()
                    ->suffix('m²')
                    ->disabled(),
                TextInput::make('superficie_util')
                    ->label('Superficie Útil')
                    ->numeric()
                    ->suffix('m²')
                    ->disabled(),
                TextInput::make('superficie_terraza')
                    ->label('Superficie Terraza')
                    ->numeric()
                    ->suffix('m²')
                    ->disabled(),
                CuratorPicker::make('cover_image_id')
                    ->label('Imagen de Portada')
                    ->helperText('Imagen principal para mostrar la planta.'),
                CuratorPicker::make('interior_image_id')
                    ->label('Imagen Interior')
                    ->helperText('Imagen interior o de detalle de la planta.'),
                TextInput::make('salesforce_interior_image_url')
                    ->label('Imagen interior (Salesforce)')
                    ->readOnly()
                    ->dehydrated(false)
                    ->suffixAction(
                        Action::make('openSalesforceInteriorImage')
                            ->icon('heroicon-o-arrow-top-right-on-square')
                            ->url(fn (?string $state): ?string => filled($state) ? $state : null, shouldOpenInNewTab: true)
                    )
                    ->helperText('Sincronizada automáticamente desde Salesforce cuando existe un documento con nombre "Proyecto - Planta".'),
                Toggle::make('is_active')
                    ->label('Activo')
                    ->required(),
                DateTimePicker::make('last_synced_at')
                    ->label('Última Sincronización')
                    ->disabled(),
            ]);
    }
}
