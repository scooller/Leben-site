<?php

namespace App\Filament\Resources\Proyectos\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProyectoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name')
                    ->label('Nombre del Proyecto')
                    ->disabled()
                    ->required(),

                Textarea::make('descripcion')
                    ->label('Descripción')
                    ->disabled()
                    ->rows(3),

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

                TextInput::make('dscto_m_x_prod_principal_porc')
                    ->label('Descuento Principal (%)')
                    ->numeric()
                    ->disabled()
                    ->suffix('%'),

                TextInput::make('dscto_m_x_prod_principal_uf')
                    ->label('Descuento Principal (UF)')
                    ->numeric()
                    ->disabled()
                    ->suffix('UF'),

                TextInput::make('dscto_m_x_bodega_porc')
                    ->label('Descuento Bodega (%)')
                    ->numeric()
                    ->disabled()
                    ->suffix('%'),

                TextInput::make('dscto_m_x_bodega_uf')
                    ->label('Descuento Bodega (UF)')
                    ->numeric()
                    ->disabled()
                    ->suffix('UF'),

                TextInput::make('dscto_m_x_estac_porc')
                    ->label('Descuento Estacionamiento (%)')
                    ->numeric()
                    ->disabled()
                    ->suffix('%'),

                TextInput::make('dscto_m_x_estac_uf')
                    ->label('Descuento Estacionamiento (UF)')
                    ->numeric()
                    ->disabled()
                    ->suffix('UF'),

                TextInput::make('dscto_max_otros_porc')
                    ->label('Descuento Otros (%)')
                    ->numeric()
                    ->disabled()
                    ->suffix('%'),

                TextInput::make('dscto_max_otros_prod_uf')
                    ->label('Descuento Otros (UF)')
                    ->numeric()
                    ->disabled()
                    ->suffix('UF'),

                TextInput::make('dscto_maximo_aporte_leben')
                    ->label('Descuento Máximo Aporte Leben')
                    ->numeric()
                    ->disabled()
                    ->suffix('%'),

                TextInput::make('n_anos_1')
                    ->label('Años Financiamiento 1')
                    ->numeric()
                    ->disabled(),

                TextInput::make('n_anos_2')
                    ->label('Años Financiamiento 2')
                    ->numeric()
                    ->disabled(),

                TextInput::make('n_anos_3')
                    ->label('Años Financiamiento 3')
                    ->numeric()
                    ->disabled(),

                TextInput::make('n_anos_4')
                    ->label('Años Financiamiento 4')
                    ->numeric()
                    ->disabled(),

                TextInput::make('valor_reserva_exigido_defecto_peso')
                    ->label('Valor Reserva Defecto ($)')
                    ->numeric()
                    ->disabled()
                    ->prefix('$'),

                TextInput::make('valor_reserva_exigido_min_peso')
                    ->label('Valor Reserva Mínimo ($)')
                    ->numeric()
                    ->disabled()
                    ->prefix('$'),

                TextInput::make('tasa')
                    ->label('Tasa')
                    ->numeric()
                    ->disabled()
                    ->suffix('%'),

                Toggle::make('entrega_inmediata')
                    ->label('Entrega Inmediata')
                    ->disabled(),
            ]);
    }
}
