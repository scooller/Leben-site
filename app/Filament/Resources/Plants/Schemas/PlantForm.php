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
                    // ->disabled()
                    ->required(),
                TextInput::make('product_code')
                    ->label('Código de Producto')
                    // ->disabled()
                    ->required(),
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
