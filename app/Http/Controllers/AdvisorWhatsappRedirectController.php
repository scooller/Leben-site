<?php

namespace App\Http\Controllers;

use App\Models\Asesor;
use App\Models\SiteSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdvisorWhatsappRedirectController extends Controller
{
	public function __invoke(Request $request, Asesor $asesor): View|RedirectResponse
	{
		if (! $asesor->is_active) {
			abort(404);
		}

		$phone = $this->sanitizePhone($asesor->whatsapp_owner);

		if ($phone === '') {
			abort(404);
		}

		$message = $this->resolveMessage($request, $asesor);
		$destinationUrl = sprintf('https://wa.me/%s?text=%s', $phone, rawurlencode($message));
		$tagManagerId = trim((string) SiteSetting::get('tag_manager_id', ''));

		if ($tagManagerId === '') {
			return redirect()->away($destinationUrl);
		}

		return view('whatsapp-links.redirect', [
			'destinationUrl' => $destinationUrl,
			'tagManagerId' => $tagManagerId,
			'redirectDelayMs' => 500,
			'eventData' => [
				'event' => 'wa_link',
				'action' => 'advisor_cta_click',
				'advisor_id' => $asesor->id,
				'advisor_name' => $asesor->full_name,
				'advisor_email' => $asesor->email,
				'plant_id' => $request->query('plant_id'),
				'plant_name' => $request->query('plant_name'),
				'project_name' => $request->query('project_name'),
				'source' => $request->query('source', 'advisor_whatsapp_redirect'),
				'destination' => $destinationUrl,
				'utm_source' => $request->query('utm_source'),
				'utm_medium' => $request->query('utm_medium'),
				'utm_campaign' => $request->query('utm_campaign'),
				'utm_term' => $request->query('utm_term'),
				'utm_content' => $request->query('utm_content'),
				'utm_site' => $request->query('utm_site'),
				'redirected_at' => now()->toISOString(),
			],
		]);
	}

	private function sanitizePhone(?string $value): string
	{
		if (blank($value)) {
			return '';
		}
		if (str_starts_with($value, '+')) {
			$value = substr($value, 1);
		}
		if (strlen($value) < 9) {
			return '';
		}
		// si el numero es igual a 9 digitos y no empieza con 56, se asume que es un numero local chileno y se le agrega el prefijo 56
		if (strlen($value) === 9 && ! str_starts_with($value, '56')) {
			$value = '56' . $value;
		}
		return preg_replace('/\D+/', '', (string) ($value ?? '')) ?: '';
	}

	private function resolveMessage(Request $request, Asesor $asesor): string
	{
		$requestedMessage = trim((string) $request->query('text', ''));

		if ($requestedMessage !== '') {
			return $requestedMessage;
		}

		$contactName = trim((string) ($asesor->first_name ?: $asesor->full_name));

		if ($contactName === '') {
			$contactName = 'asesor';
		}

		return sprintf('Hola %s', $contactName);
	}
}
