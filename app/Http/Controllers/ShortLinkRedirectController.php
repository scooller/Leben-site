<?php

namespace App\Http\Controllers;

use App\Enums\ShortLinkStatus;
use App\Jobs\RecordShortLinkVisitJob;
use App\Models\ShortLink;
use App\Services\ShortLink\ShortLinkService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShortLinkRedirectController extends Controller
{
    public function __construct(
        private readonly ShortLinkService $shortLinkService,
    ) {}

    public function __invoke(Request $request, string $slug): View|RedirectResponse
    {
        $shortLink = ShortLink::query()->where('slug', $slug)->firstOrFail();

        $resolvedStatus = $this->shortLinkService->resolveStatusForRedirect($shortLink);

        if ($resolvedStatus !== ShortLinkStatus::ACTIVE) {
            abort(404);
        }

        $destinationUrl = $this->shortLinkService->withForwardedQuery(
            destinationUrl: $shortLink->destination_url,
            query: $request->query(),
        );

        RecordShortLinkVisitJob::dispatch($shortLink->id, [
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'referer' => (string) $request->headers->get('referer', ''),
            'utm_source' => $request->query('utm_source'),
            'utm_medium' => $request->query('utm_medium'),
            'utm_campaign' => $request->query('utm_campaign'),
            'utm_term' => $request->query('utm_term'),
            'utm_content' => $request->query('utm_content'),
            'session_fingerprint' => Str::random(32),
            'query_params' => $request->query(),
        ]);

        $resolvedTagManagerId = $this->shortLinkService->resolveTagManagerId($shortLink);

        if ($resolvedTagManagerId === null) {
            return redirect()->away($destinationUrl);
        }

        return view('short-links.redirect', [
            'shortLink' => $shortLink,
            'destinationUrl' => $destinationUrl,
            'tagManagerId' => $resolvedTagManagerId,
            'redirectDelayMs' => 150,
        ]);
    }
}
