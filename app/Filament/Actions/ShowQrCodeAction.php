<?php

namespace App\Filament\Actions;

use App\Models\SiteSetting;
use Filament\Actions\Action;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use LaraZeus\Qr\Facades\Qr;

class ShowQrCodeAction
{
	public static function make(string|callable $urlResolver, ?string $name = 'showQrCode'): Action
	{
		return Action::make($name)
			->label('Ver QR')
			->icon('heroicon-o-qr-code')
			->color('gray')
			->disabled(fn($record): bool => empty(value($urlResolver, $record)))
			->modalHeading('Codigo QR')
			->modalDescription('Escanea o copia la URL asociada a este enlace.')
			->modalSubmitAction(false)
			->modalCancelActionLabel('Cerrar')
			->modalContent(function (Model $record) use ($urlResolver): View {
				$url = value($urlResolver, $record);
				$qrOptions = SiteSetting::current()->qrOptions();
				$qrOptions['type'] = 'svg';
				$qrSvg = (string) Qr::render(data: $url, options: $qrOptions, downloadable: false);
				$qrDownloadSvg = self::extractSvgMarkup($qrSvg);
				$recordKey = (string) ($record->getKey() ?? 'item');
				$downloadName = sprintf('qr-%s.svg', $recordKey);

				return view('filament.actions.show-qr-code', [
					'url' => $url,
					'qrSvg' => $qrSvg,
					'qrDownloadUrl' => 'data:image/svg+xml;base64,' . base64_encode($qrDownloadSvg),
					'qrDownloadName' => $downloadName,
				]);
			})
			->action(static fn(): null => null);
	}

	public static function extractSvgMarkup(string $html): string
	{
		if (preg_match('/<svg\b[^>]*>.*<\/svg>/is', $html, $matches) === 1) {
			return $matches[0];
		}

		return $html;
	}
}
