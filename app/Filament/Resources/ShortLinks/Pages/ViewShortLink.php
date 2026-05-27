<?php

namespace App\Filament\Resources\ShortLinks\Pages;

use App\Filament\Actions\ShowQrCodeAction;
use App\Filament\Resources\ShortLinks\ShortLinkResource;
use App\Models\ShortLink;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewShortLink extends ViewRecord
{
    protected static string $resource = ShortLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openShortUrl')
                ->label('Abrir URL corta')
                ->icon('heroicon-o-link')
                ->url(fn(): string => $this->getRecord()->shortUrl(), true),
            ShowQrCodeAction::make(fn(ShortLink $record): string => $record->shortUrl()),
            EditAction::make(),
            DeleteAction::make()
                ->label('Eliminar')
                ->modalHeading('Eliminar link corto')
                ->modalDescription('Esta accion eliminara el link corto de forma permanente.')
                ->successNotificationTitle('Link corto eliminado correctamente'),
        ];
    }

    public function getSubheading(): ?string
    {
        /** @var ShortLink $record */
        $record = $this->getRecord();

        return sprintf(
            'Visitas totales: %d | Ultima visita: %s',
            (int) $record->visits_count,
            $record->last_visited_at?->format('d/m/Y H:i') ?? 'sin visitas'
        );
    }
}
