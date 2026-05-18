<?php

namespace App\Models;

use App\Support\LogsModelActivity;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactSubmission extends Model
{
    use LogsModelActivity;

    protected $fillable = [
        'contact_channel_id',
        'name',
        'email',
        'phone',
        'rut',
        'fields',
        'recipient_email',
        'ip_address',
        'user_agent',
        'submitted_at',
        'salesforce_case_id',
        'salesforce_case_error',
        'salesforce_synced_at',
        'salesforce_sync_trigger',
    ];

    protected $casts = [
        'fields' => 'array',
        'submitted_at' => 'datetime',
        'salesforce_synced_at' => 'datetime',
    ];

    public function setPhoneAttribute(mixed $value): void
    {
        if ($value === null) {
            $this->attributes['phone'] = null;

            return;
        }

        $normalized = (string) preg_replace('/\D+/', '', (string) $value);
        $this->attributes['phone'] = $normalized !== '' ? $normalized : null;
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(ContactChannel::class, 'contact_channel_id');
    }

    public function salesforceLeadUrl(): ?string
    {
        $leadId = trim((string) $this->salesforce_case_id);

        if ($leadId === '' || preg_match('/^[a-zA-Z0-9]{15,18}$/', $leadId) !== 1) {
            return null;
        }

        $instanceUrl = rtrim((string) config('services.salesforce.instance_url'), '/');

        if ($instanceUrl === '') {
            return null;
        }

        return sprintf('%s/lightning/r/Lead/%s/view', $instanceUrl, $leadId);
    }

    public function hasSalesforceSyncInfo(): bool
    {
        return filled($this->salesforce_case_id)
            || filled($this->salesforce_case_error)
            || $this->salesforce_synced_at !== null
            || filled($this->salesforce_sync_trigger);
    }

    public function salesforceSyncedAt(): ?CarbonInterface
    {
        if (! $this->hasSalesforceSyncInfo()) {
            return null;
        }

        return $this->salesforce_synced_at ?? $this->updated_at;
    }

    public function salesforceSyncModeLabel(): ?string
    {
        if (! $this->hasSalesforceSyncInfo()) {
            return null;
        }

        $syncTrigger = trim((string) $this->salesforce_sync_trigger);

        if ($syncTrigger === 'manual') {
            return 'Manual';
        }

        if ($syncTrigger === 'automatic') {
            return 'Automática';
        }

        if ($this->submitted_at === null || $this->updated_at === null) {
            return null;
        }

        return $this->updated_at->greaterThan($this->submitted_at->copy()->addSeconds(30))
            ? 'Manual'
            : 'Automática';
    }
}
