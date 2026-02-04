<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesforceLead extends Model
{
    protected $fillable = [
        'salesforce_id',
        'first_name',
        'last_name',
        'email',
        'company',
        'status',
        'phone',
        'country',
        'raw_data',
        'last_synced_at',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Scopes
     */
    public function scopeBySalesforceId($query, string $salesforceId)
    {
        return $query->where('salesforce_id', $salesforceId);
    }

    public function scopeByEmail($query, string $email)
    {
        return $query->where('email', $email);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
