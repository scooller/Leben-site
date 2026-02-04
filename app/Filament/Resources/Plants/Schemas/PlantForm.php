<?php

namespace App\Filament\Resources\Plants\Schemas;

use App\Models\Proyecto;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PlantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('salesforce_product_id')
                    ->label('Salesforce Product ID')
                    ->required()
                    ->disabled(),
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->disabled(),
                TextInput::make('product_code')
                    ->label('Código de Producto')
                    ->required()
                    ->disabled(),
                Select::make('salesforce_proyecto_id')
                    ->label('Proyecto')
                    ->options(Proyecto::pluck('name', 'salesforce_id'))
                    ->disabled()
                    ->searchable(),
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
                    ->disabled(),
                TextInput::make('precio_lista')
                    ->label('Precio Lista')
                    ->numeric()
                    ->prefix('$')
                    ->disabled(),
                TextInput::make('precio_venta')
                    ->label('Precio Venta')
                    ->numeric()
                    ->prefix('$'),
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
                TextInput::make('superficie_vendible')
                    ->label('Superficie Vendible')
                    ->numeric()
                    ->suffix('m²')
                    ->disabled(),
                TextInput::make('opportunity_id')
                    ->label('Opportunity ID')
                    ->disabled(),
                Toggle::make('is_active')
                    ->label('Activo')
                    ->required(),
                DateTimePicker::make('last_synced_at')
                    ->label('Última Sincronización')
                    ->disabled(),
            ]);
    }
}

