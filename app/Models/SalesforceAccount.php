<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesforceAccount extends Model
{
    protected $fillable = [
        'salesforce_id',
        'name',
        'type',
        'billing_country',
        'billing_city',
        'industry',
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

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
