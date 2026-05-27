<?php

namespace App\Filament\Resources\Asesores\Tables;

use App\Enums\ShortLinkStatus;
use App\Models\Asesor;
use App\Models\ShortLink;
use App\Models\SiteSetting;
use App\Services\ShortLink\ShortLinkService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use LaraZeus\Qr\Facades\Qr;

class AsesoresTable
{
	public static function configure(Table $table): Table
	{
		return $table
			->defaultSort('first_name', 'asc')
			->columns([
				TextColumn::make('full_name')
					->label('Nombre')
					->searchable(['first_name', 'last_name'])
					->sortable(['first_name', 'last_name']),

				ImageColumn::make('avatar_url')
					->label('Avatar')
					->getStateUsing(fn($record): ?string => $record->resolved_avatar_url)
					->circular(),

				TextColumn::make('email')
					->label('Email')
					->searchable()
					->copyable(),

				TextColumn::make('whatsapp_owner')
					->label('WhatsApp')
					->searchable(),

				TextColumn::make('qr_short_url')
					->label('Link QR')
					->state(fn(Asesor $record): ?string => self::resolveExistingWhatsappShortLinkUrl($record))
					->placeholder('Sin link QR')
					->copyable(fn(?string $state): bool => filled($state))
					->toggleable(isToggledHiddenByDefault: true),

				TextColumn::make('salesforce_id')
					->label('Salesforce ID')
					->searchable()
					->toggleable(isToggledHiddenByDefault: true),

				TextColumn::make('proyectos_count')
					->label('Proyectos')
					->counts('proyectos')
					->sortable(),

				TextColumn::make('updated_at')
					->label('Actualizado')
					->dateTime('d/m/Y H:i')
					->sortable()
					->toggleable(isToggledHiddenByDefault: true),
			])
			->filters([
				SelectFilter::make('is_active')
					->label('Estado')
					->options([
						1 => 'Activo',
						0 => 'Inactivo',
					]),
			])
			->recordActions([
				Action::make('createAdvisorQr')
					->label('Crear QR')
					->icon('heroicon-o-qr-code')
					->color('gray')
					->disabled(fn(Asesor $record): bool => ! $record->is_active || blank($record->whatsapp_owner))
					->tooltip(fn(Asesor $record): ?string => (! $record->is_active || blank($record->whatsapp_owner))
						? 'El asesor debe estar activo y con WhatsApp para generar su QR.'
						: null)
					->modalHeading('Codigo QR del asesor')
					->modalDescription('Genera automaticamente un link corto al redirect de WhatsApp del asesor y muestra su QR.')
					->modalSubmitAction(false)
					->modalCancelActionLabel('Cerrar')
					->modalContent(function (Asesor $record): View {
						$shortUrl = self::resolveOrCreateWhatsappShortLinkUrl($record);
						$qrOptions = SiteSetting::current()->qrOptions();
						$qrOptions['type'] = 'svg';

						return view('filament.actions.show-qr-code', [
							'url' => $shortUrl,
							'qrSvg' => Qr::render(data: $shortUrl, options: $qrOptions, downloadable: false),
						]);
					})
					->action(static fn(): null => null),
				EditAction::make(),
			])
			->toolbarActions([
				BulkActionGroup::make([
					DeleteBulkAction::make(),
				]),
			]);
	}

	public static function resolveOrCreateWhatsappShortLinkUrl(Asesor $asesor): string
	{
		/** @var ShortLinkService $shortLinkService */
		$shortLinkService = app(ShortLinkService::class);

		$destinationUrl = $shortLinkService->normalizeAndValidateDestinationUrl(
			route('advisors.whatsapp.redirect', ['asesor' => $asesor])
		);

		$existingShortLink = self::resolveExistingWhatsappShortLink($asesor, $destinationUrl);

		if ($existingShortLink instanceof ShortLink) {
			return $existingShortLink->shortUrl();
		}

		$createdShortLink = ShortLink::query()->create([
			'created_by' => Auth::id(),
			'slug' => $shortLinkService->generateUniqueSlug(),
			'title' => sprintf('QR WhatsApp asesor: %s', $asesor->full_name),
			'destination_url' => $destinationUrl,
			'status' => ShortLinkStatus::ACTIVE,
			'metadata' => [
				'origin' => 'advisor_whatsapp_qr',
				'advisor_id' => $asesor->id,
				'advisor_name' => $asesor->full_name,
			],
		]);

		return $createdShortLink->shortUrl();
	}

	public static function resolveExistingWhatsappShortLinkUrl(Asesor $asesor): ?string
	{
		/** @var ShortLinkService $shortLinkService */
		$shortLinkService = app(ShortLinkService::class);

		$destinationUrl = $shortLinkService->normalizeAndValidateDestinationUrl(
			route('advisors.whatsapp.redirect', ['asesor' => $asesor])
		);

		$shortLink = self::resolveExistingWhatsappShortLink($asesor, $destinationUrl);

		if (! $shortLink instanceof ShortLink) {
			return null;
		}

		return $shortLink->shortUrl();
	}

	private static function resolveExistingWhatsappShortLink(Asesor $asesor, string $destinationUrl): ?ShortLink
	{
		return ShortLink::query()
			->where('destination_url', $destinationUrl)
			->where('metadata->origin', 'advisor_whatsapp_qr')
			->where('metadata->advisor_id', $asesor->id)
			->latest('id')
			->first();
	}
}
