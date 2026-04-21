<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactSubmission extends Model
{
    protected $fillable = [
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
