<?php

namespace App\Models;

use App\Support\LogsModelActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactChannel extends Model
{
    /** @use HasFactory<\Database\Factories\ContactChannelFactory> */
    use HasFactory, LogsModelActivity;

    protected $fillable = [
        'slug',
        'name',
        'is_active',
        'is_default',
        'notification_email',
        'form_fields',
        'domain_patterns',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'form_fields' => 'array',
            'domain_patterns' => 'array',
        ];
    }

    public function contactSubmissions(): HasMany
    {
        return $this->hasMany(ContactSubmission::class, 'contact_channel_id');
    }

    /**
     * Finds an active channel by its slug.
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Finds the first active channel whose domain_patterns match the given domain.
     */
    public static function findByDomain(string $domain): ?self
    {
        $domain = strtolower(trim($domain));

        if ($domain === '') {
            return null;
        }

        return static::query()
            ->where('is_active', true)
            ->where('is_default', false)
            ->get()
            ->first(function (self $channel) use ($domain): bool {
                foreach ((array) ($channel->domain_patterns ?? []) as $pattern) {
                    $pattern = strtolower(trim((string) $pattern));

                    if ($pattern === '') {
                        continue;
                    }

                    if (str_contains($pattern, '*')) {
                        $regex = '/^'.str_replace('\*', '.*', preg_quote($pattern, '/')).'$/i';
                        if (preg_match($regex, $domain) === 1) {
                            return true;
                        }
                    } elseif ($pattern === $domain || str_ends_with($domain, '.'.$pattern)) {
                        return true;
                    }
                }

                return false;
            });
    }

    /**
     * Returns the single active default channel, or null if none configured.
     */
    public static function getDefault(): ?self
    {
        return static::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Returns the effective form fields for this channel,
     * falling back to SiteSetting::current() fields when not overridden.
     *
     * @return array<int, array<string, mixed>>
     */
    public function effectiveFormFields(): array
    {
        if (is_array($this->form_fields) && $this->form_fields !== []) {
            return $this->form_fields;
        }

        $fields = SiteSetting::current()->contact_form_fields;

        return is_array($fields) ? $fields : [];
    }

    /**
     * Returns the effective notification email for this channel,
     * falling back to SiteSetting global config.
     */
    public function effectiveNotificationEmail(): ?string
    {
        if (filled($this->notification_email)) {
            return $this->notification_email;
        }

        $settings = SiteSetting::current();

        return $settings->contact_notification_email ?: $settings->contact_email ?: null;
    }
}
