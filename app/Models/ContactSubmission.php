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
}
