<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesforceOpportunity extends Model
{
    use HasFactory;

    protected $fillable = [
        'salesforce_id',
        'broker_id',
        'proyecto_id',
        'broker_salesforce_id',
        'proyecto_salesforce_id',
        'account_salesforce_id',
        'contact_salesforce_id',
        'owner_salesforce_id',
        'name',
        'broker_name',
        'proyecto_name',
        'stage_name',
        'forecast_category_name',
        'currency_iso_code',
        'amount',
        'probability',
        'is_closed',
        'is_won',
        'is_deleted',
        'is_private',
        'close_date',
        'salesforce_created_at',
        'salesforce_last_modified_at',
        'salesforce_system_modstamp',
        'synced_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'probability' => 'decimal:2',
            'is_closed' => 'boolean',
            'is_won' => 'boolean',
            'is_deleted' => 'boolean',
            'is_private' => 'boolean',
            'close_date' => 'date',
            'salesforce_created_at' => 'datetime',
            'salesforce_last_modified_at' => 'datetime',
            'salesforce_system_modstamp' => 'datetime',
            'synced_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function broker(): BelongsTo
    {
        return $this->belongsTo(Broker::class);
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }
}
