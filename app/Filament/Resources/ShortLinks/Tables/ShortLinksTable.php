<?php

namespace App\Filament\Resources\ShortLinks\Tables;

use App\Enums\ShortLinkStatus;
use App\Filament\Actions\ExportShortLinksAction;
use App\Filament\Actions\ShowQrCodeAction;
use App\Models\ShortLink;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ShortLinksTable
{
	public static function configure(Table $table): Table
	{
		return $table
			->columns([
				TextColumn::make('short_url')
					->label('URL corta')
					->state(fn(ShortLink $record): string => $record->shortUrl())
					->copyable()
					->toggleable(),
				TextColumn::make('slug')
					->label('Slug')
					->badge()
					->copyable()
					->searchable()
					->sortable()
					->toggleable(isToggledHiddenByDefault: true),
				TextColumn::make('destination_url')
					->label('Destino')
					->searchable()
					->limit(60)
					->tooltip(fn(ShortLink $record): string => $record->destination_url),
				TextColumn::make('status')
					->label('Estado')
					->badge()
					->color(function (ShortLinkStatus|string|null $state): string {
						$status = $state instanceof ShortLinkStatus
							? $state
							: ShortLinkStatus::fromValue((string) $state);

						return $status?->color() ?? 'gray';
					})
					->icon(function (ShortLinkStatus|string|null $state): string {
						$status = $state instanceof ShortLinkStatus
							? $state
							: ShortLinkStatus::fromValue((string) $state);

						return $status?->icon() ?? 'heroicon-o-question-mark-circle';
					})
					->formatStateUsing(function (ShortLinkStatus|string|null $state): string {
						$status = $state instanceof ShortLinkStatus
							? $state
							: ShortLinkStatus::fromValue((string) $state);

						return $status?->label() ?? '-';
					})
					->sortable(),
				TextColumn::make('visits_count')
					->label('Visitas')
					->numeric()
					->sortable(),
				TextColumn::make('last_visited_at')
					->label('Ultima visita')
					->dateTime('d/m/Y H:i')
					->placeholder('-')
					->sortable()
					->toggleable(isToggledHiddenByDefault: true),
				TextColumn::make('expires_at')
					->label('Expira')
					->dateTime('d/m/Y H:i')
					->placeholder('-')
					->sortable(),
				TextColumn::make('creator.name')
					->label('Creado por')
					->placeholder('Sistema')
					->toggleable(),
				TextColumn::make('created_at')
					->label('Creado')
					->dateTime('d/m/Y H:i')
					->sortable(),
			])
			->filters([
				SelectFilter::make('status')
					->label('Estado')
					->options(ShortLinkStatus::toSelectArray())
					->searchable(),
				SelectFilter::make('created_by')
					->label('Creado por')
					->relationship('creator', 'name')
					->searchable(),
			])
			->recordActions([
				Action::make('openShortUrl')
					->label('Abrir URL corta')
					->icon('heroicon-o-link')
					->url(fn(ShortLink $record): string => $record->shortUrl(), true),
				ShowQrCodeAction::make(fn(ShortLink $record): string => $record->shortUrl()),
				ViewAction::make(),
				EditAction::make(),
				DeleteAction::make()
					->label('Eliminar')
					->modalHeading('Eliminar link corto')
					->modalDescription('Esta accion eliminara el link corto de forma permanente.')
					->successNotificationTitle('Link corto eliminado correctamente'),
			])
			->defaultSort('created_at', 'desc')
			->toolbarActions([
				ExportShortLinksAction::make(),
				BulkActionGroup::make([
					DeleteBulkAction::make(),
				]),
			]);
	}
}
