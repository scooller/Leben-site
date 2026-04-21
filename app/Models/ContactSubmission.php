<?php

namespace App\Models;

use App\Support\LogsModelActivity;
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
    ];

    protected $casts = [
        'fields' => 'array',
        'submitted_at' => 'datetime',
    ];

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
}
