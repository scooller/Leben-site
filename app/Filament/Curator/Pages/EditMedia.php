<?php

declare(strict_types=1);

namespace App\Filament\Curator\Pages;

use App\Filament\Curator\MediaResource;
use App\Filament\Curator\Tables\MediaTable;
use App\Models\SiteSetting;
use Awcodes\Curator\Resources\Media\Pages\EditMedia as BaseEditMedia;
use Exception;
use Filament\Actions\Action;
use Illuminate\Contracts\View\View;
use LaraZeus\Qr\Facades\Qr;

class EditMedia extends BaseEditMedia
{
    protected static string $resource = MediaResource::class;

    /** @throws Exception */
    public function getHeaderActions(): array
    {
        return [
            Action::make('showQr')
                ->label('Ver QR')
                ->icon('heroicon-o-qr-code')
                ->color('gray')
                ->modalHeading('Código QR del archivo')
                ->modalDescription('Genera un short-link para el archivo y agrega UTMs para tracking automatico.')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->modalContent(function (): View {
                    $qrUrl = MediaTable::resolveMediaQrUrl($this->record);
                    $qrOptions = SiteSetting::current()->qrOptions();
                    $qrOptions['type'] = 'svg';
                    $qrSvg = (string) Qr::render(data: $qrUrl, options: $qrOptions, downloadable: false);
                    $downloadName = sprintf('qr-archivo-%s.svg', $this->record->id);

                    return view('filament.actions.show-qr-code', [
                        'url' => $qrUrl,
                        'qrSvg' => $qrSvg,
                        'qrDownloadUrl' => 'data:image/svg+xml;base64,'.base64_encode($qrSvg),
                        'qrDownloadName' => $downloadName,
                    ]);
                })
                ->action(static fn (): null => null),
            ...parent::getHeaderActions(),
        ];
    }
}
