<?php

namespace App\Filament\Resources\Asesores\RelationManagers;

use App\Filament\Resources\ProyectoResource;
use App\Models\Asesor;
use App\Models\Proyecto;
use App\Support\AsesorProyectoActivityLogger;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProyectosRelationManager extends RelationManager
{
    protected static string $relationship = 'proyectos';

    protected static ?string $title = 'Proyectos a cargo';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(function (Builder $query): Builder {
                return $query
                    ->with([
                        'plantas' => fn (HasMany $plantasQuery): HasMany => $plantasQuery
                            ->select(['id', 'salesforce_proyecto_id', 'name'])
                            ->orderBy('name'),
                    ]);
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Proyecto')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('comuna')
                    ->label('Comuna')
                    ->sortable(),
                Tables\Columns\TextColumn::make('etapa')
                    ->label('Etapa')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('plantas_count')
                    ->label('Plantas')
                    ->counts('plantas')
                    ->sortable(),
                Tables\Columns\TextColumn::make('plantas.name')
                    ->label('Plantas asociadas')
                    ->badge()
                    ->separator(',')
                    ->limitList(5)
                    ->expandableLimitedList()
                    ->placeholder('Sin plantas'),
            ])
            ->filters([])
            ->recordActions([
                Action::make('edit_project')
                    ->label('Editar proyecto')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Proyecto $record): string => ProyectoResource::getUrl('edit', ['record' => $record])),
                DetachAction::make()
                    ->label('Quitar asesor')
                    ->after(function (Proyecto $record, ProyectosRelationManager $livewire): void {
                        $asesor = $livewire->getOwnerRecord();

                        if (! $asesor instanceof Asesor) {
                            return;
                        }

                        AsesorProyectoActivityLogger::logDetached($asesor, [$record]);
                    }),
            ])
            ->toolbarActions([
                AttachAction::make()
                    ->label('Asignar proyectos')
                    ->multiple()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'comuna', 'region'])
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => $query->orderBy('name'))
                    ->after(function (array $data, ProyectosRelationManager $livewire): void {
                        $asesor = $livewire->getOwnerRecord();

                        if (! $asesor instanceof Asesor) {
                            return;
                        }

                        $recordIds = collect((array) ($data['recordId'] ?? []))
                            ->map(static fn ($id): int => (int) $id)
                            ->filter()
                            ->values();

                        if ($recordIds->isEmpty()) {
                            return;
                        }

                        $proyectos = Proyecto::query()
                            ->whereIn('id', $recordIds->all())
                            ->get(['id', 'name']);

                        AsesorProyectoActivityLogger::logAttached($asesor, $proyectos);
                    }),
            ])
            ->bulkActions([
                DetachBulkAction::make()
                    ->label('Quitar asesores de proyectos seleccionados')
                    ->after(function (EloquentCollection $records, ProyectosRelationManager $livewire): void {
                        $asesor = $livewire->getOwnerRecord();

                        if (! $asesor instanceof Asesor) {
                            return;
                        }

                        AsesorProyectoActivityLogger::logDetached($asesor, $records);
                    }),
            ])
            ->paginated([10, 25, 50])
            ->defaultSort('name');
    }
}
