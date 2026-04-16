<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

class FrontendPreviewLink extends Model
{
    protected $fillable = [
        'name',
        'token',
        'preview_path',
        'allowed_ip',
        'expires_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $builder): void {
            $builder->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public static function isAuthorizedForRequest(Request $request): bool
    {
        $plainToken = trim((string) $request->query('preview_token', $request->headers->get('X-Preview-Token')));

        if ($plainToken === '') {
            return false;
        }

        $previewLink = static::query()
            ->active()
            ->where('token', $plainToken)
            ->first();

        if (! $previewLink instanceof self) {
            return false;
        }

        return $previewLink->allowsIp($request->ip());
    }

    public function allowsIp(?string $requestIp): bool
    {
        if (blank($this->allowed_ip)) {
            return true;
        }

        if (blank($requestIp)) {
            return false;
        }

        $allowedIps = collect(preg_split('/[\s,]+/', (string) $this->allowed_ip) ?: [])
            ->map(static fn (string $ip): string => trim($ip))
            ->filter(static fn (string $ip): bool => $ip !== '')
            ->values();

        foreach ($allowedIps as $allowedIp) {
            if ($requestIp === $allowedIp || IpUtils::checkIp($requestIp, $allowedIp)) {
                return true;
            }
        }

        return false;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function normalizedPreviewPath(): string
    {
        $previewPath = trim((string) ($this->preview_path ?: '/'));
        $previewPath = $previewPath === '' ? '/' : $previewPath;

        if (! str_starts_with($previewPath, '/')) {
            $previewPath = '/'.$previewPath;
        }

        return $previewPath;
    }

    public function previewUrl(): string
    {
        $baseUrl = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');
        $previewPath = $this->normalizedPreviewPath();
        $separator = str_contains($previewPath, '?') ? '&' : '?';

        return $baseUrl.$previewPath.$separator.'preview_token='.$this->token;
    }
}
